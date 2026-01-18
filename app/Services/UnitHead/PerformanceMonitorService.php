<?php

namespace App\Services\UnitHead;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerformanceMonitorService
{
    public function __construct(
        private readonly PerformanceScoreService $scoreSvc,
    ) {
    }

    public function resolveMode(AssessmentPeriod $period, ?string $requestedMode): string
    {
        // Frozen periods MUST use snapshot to keep results stable.
        if ($period->isFrozen()) {
            return 'snapshot';
        }

        $requestedMode = strtolower(trim((string) $requestedMode));
        if (in_array($requestedMode, ['live', 'snapshot'], true)) {
            return $requestedMode;
        }

        return 'live';
    }

    /**
     * @return array{q:string,status:string,profession_id:?int}
     */
    private function normalizeFilters(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $professionId = $filters['profession_id'] ?? null;
        $professionId = $professionId === null || $professionId === '' ? null : (int) $professionId;

        return [
            'q' => $q,
            'status' => $status,
            'profession_id' => $professionId,
        ];
    }

    private function mapValidationStatusFilter(string $status): ?string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        return match ($status) {
            'pending', 'menunggu', 'menunggu_validasi' => AssessmentValidationStatus::PENDING->value,
            'validated', 'valid', 'tervalidasi' => AssessmentValidationStatus::VALIDATED->value,
            'rejected', 'ditolak' => AssessmentValidationStatus::REJECTED->value,
            default => null,
        };
    }

    /**
     * List anggota unit (read-only) + status kinerja + skor WSM.
     *
     * @param array{q?:string,search?:string,profession_id?:int|string|null,status?:string} $filters
     */
    public function paginateUnitMembers(User $actor, AssessmentPeriod $period, string $mode, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $unitId = (int) ($actor->unit_id ?? 0);
        if ($unitId <= 0) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $filters = $this->normalizeFilters($filters);
        $periodId = (int) $period->id;

        // Base query differs between live vs snapshot membership.
        if ($mode === 'snapshot') {
            $q = DB::table('assessment_period_user_membership_snapshots as ms')
                ->join('users as u', 'u.id', '=', 'ms.user_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->leftJoin('units as un', 'un.id', '=', 'ms.unit_id')
                ->leftJoin('performance_assessments as pa', function ($j) use ($periodId) {
                    $j->on('pa.user_id', '=', 'u.id')->where('pa.assessment_period_id', '=', $periodId);
                })
                ->where('ms.assessment_period_id', $periodId)
                ->where('ms.unit_id', $unitId)
                ->select([
                    'u.id',
                    'u.name',
                    'u.employee_number',
                    'u.profession_id',
                    'ms.unit_id',
                    DB::raw("COALESCE(p.name, '-') as profession_name"),
                    DB::raw("COALESCE(un.name, '-') as unit_name"),
                    'pa.validation_status',
                    'pa.total_wsm_score',
                    'pa.updated_at as performance_updated_at',
                ]);

            if ($filters['profession_id'] !== null) {
                $q->where('ms.profession_id', (int) $filters['profession_id']);
            }
        } else {
            $q = DB::table('users as u')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->leftJoin('units as un', 'un.id', '=', 'u.unit_id')
                ->leftJoin('performance_assessments as pa', function ($j) use ($periodId) {
                    $j->on('pa.user_id', '=', 'u.id')->where('pa.assessment_period_id', '=', $periodId);
                })
                ->where('u.unit_id', $unitId)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('role_user as ru')
                        ->join('roles as r', 'r.id', '=', 'ru.role_id')
                        ->whereColumn('ru.user_id', 'u.id')
                        ->where('r.slug', User::ROLE_PEGAWAI_MEDIS);
                })
                ->select([
                    'u.id',
                    'u.name',
                    'u.employee_number',
                    'u.profession_id',
                    'u.unit_id',
                    DB::raw("COALESCE(p.name, '-') as profession_name"),
                    DB::raw("COALESCE(un.name, '-') as unit_name"),
                    'pa.validation_status',
                    'pa.total_wsm_score',
                    'pa.updated_at as performance_updated_at',
                ]);

            if ($filters['profession_id'] !== null) {
                $q->where('u.profession_id', (int) $filters['profession_id']);
            }
        }

        if ($filters['q'] !== '') {
            $needle = '%' . str_replace('%', '\\%', $filters['q']) . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('u.name', 'like', $needle)
                    ->orWhere('u.employee_number', 'like', $needle);
            });
        }

        // Status filter
        $status = strtolower(trim((string) $filters['status']));
        if ($status === 'empty' || $status === 'belum' || $status === 'belum_dinilai') {
            $q->whereNull('pa.total_wsm_score');
        } elseif ($status === 'filled' || $status === 'terisi') {
            $q->whereNotNull('pa.total_wsm_score');
        } else {
            $val = $this->mapValidationStatusFilter($filters['status']);
            if ($val !== null) {
                $q->where('pa.validation_status', $val);
            }
        }

        $paginator = $q
            ->orderBy('u.name')
            ->paginate($perPage)
            ->withQueryString();

        // For live mode: compute scores from the scoring engine so the table can show scores
        // even when performance_assessments have not been initialized/recalculated.
        if ($mode === 'live') {
            $items = collect($paginator->items());
            $userIds = $items->pluck('id')->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
            if (!empty($userIds)) {
                $unitId = (int) ($actor->unit_id ?? 0);
                $professionIds = $items->pluck('profession_id')->map(fn ($v) => $v === null ? null : (int) $v)->unique()->values()->all();

                $scoresByUserId = [];
                foreach ($professionIds as $pid) {
                    $pid = $pid === null ? null : (int) $pid;
                    $peerIds = $this->resolveMemberUserIds($actor, $period, 'live', $pid);
                    if (empty($peerIds)) {
                        continue;
                    }

                    $calc = $this->scoreSvc->calculate($unitId, $period, $peerIds, $pid);
                    $users = (array) ($calc['users'] ?? []);
                    foreach ($userIds as $uid) {
                        if (!array_key_exists($uid, $users)) {
                            continue;
                        }
                        $row = (array) $users[$uid];
                        $rel = $row['total_wsm_relative'] ?? ($row['total_wsm'] ?? null);
                        $scoresByUserId[$uid] = $rel === null ? null : (float) $rel;
                    }
                }

                $items = $items->map(function ($item) use ($scoresByUserId) {
                    $uid = (int) ($item->id ?? 0);
                    if ($uid > 0 && array_key_exists($uid, $scoresByUserId)) {
                        $item->total_wsm_score = $scoresByUserId[$uid];
                    }
                    return $item;
                })->all();

                $paginator->setCollection(collect($items));
            }
        }

        // For snapshot mode: prefer score from snapshot payload when available.
        if ($mode === 'snapshot' && Schema::hasTable('performance_assessment_snapshots')) {
            $userIds = collect($paginator->items())->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (!empty($userIds)) {
                $rows = DB::table('performance_assessment_snapshots')
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('user_id', $userIds)
                    ->get(['user_id', 'payload', 'snapshotted_at']);

                $snapScore = [];
                $snapAt = [];
                foreach ($rows as $r) {
                    $payload = is_string($r->payload) ? json_decode($r->payload, true) : (array) $r->payload;
                    if (!is_array($payload)) {
                        continue;
                    }
                    $calc = (array) ($payload['calc'] ?? []);
                    $rel = $calc['total_wsm_relative'] ?? ($calc['user']['total_wsm_relative'] ?? ($calc['user']['total_wsm'] ?? null));
                    $snapScore[(int) $r->user_id] = $rel === null ? null : (float) $rel;
                    $snapAt[(int) $r->user_id] = $r->snapshotted_at ?? null;
                }

                $items = collect($paginator->items())->map(function ($item) use ($snapScore, $snapAt) {
                    $uid = (int) ($item->id ?? 0);
                    if ($uid > 0 && array_key_exists($uid, $snapScore)) {
                        $item->total_wsm_score = $snapScore[$uid];
                        $item->snapshot_at = $snapAt[$uid] ?? null;
                    }
                    return $item;
                })->all();

                $paginator->setCollection(collect($items));
            }
        }

        return $paginator;
    }

    /**
     * @return array<int>
     */
    public function resolveMemberUserIds(User $actor, AssessmentPeriod $period, string $mode, ?int $professionId = null): array
    {
        $unitId = (int) ($actor->unit_id ?? 0);
        if ($unitId <= 0) {
            return [];
        }

        $periodId = (int) $period->id;

        if ($mode === 'snapshot') {
            if (!Schema::hasTable('assessment_period_user_membership_snapshots')) {
                return [];
            }

            $q = DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', $periodId)
                ->where('unit_id', $unitId);

            if ($professionId !== null) {
                $q->where('profession_id', (int) $professionId);
            }

            return $q->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        }

        return User::query()
            ->where('unit_id', $unitId)
            ->when($professionId !== null, fn ($q) => $q->where('profession_id', (int) $professionId))
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array{
     *   calculation_source:string,
     *   snapshotted_at:mixed,
     *   total_wsm_relative:?float,
     *   total_wsm_value:?float,
     *   sum_weight:float,
     *   criteria: array<int,array<string,mixed>>,
     *   assessment:?PerformanceAssessment,
     *   latest_approval:?array<string,mixed>,
     *   snapshot_detail_missing:bool,
     * }
     */
    public function getUserPerformanceDetail(User $actor, AssessmentPeriod $period, User $target, string $mode): array
    {
        $unitId = (int) ($actor->unit_id ?? 0);
        $professionId = $target->profession_id === null ? null : (int) $target->profession_id;

        // For snapshot: load only the target user's snapshot payload (stable, no need full peers).
        // For live: compute using full peer group (same unit + same profession) to keep normalization correct.
        $userIdsForCalc = $mode === 'snapshot'
            ? [(int) $target->id]
            : $this->resolveMemberUserIds($actor, $period, 'live', $professionId);

        if (empty($userIdsForCalc)) {
            $userIdsForCalc = [(int) $target->id];
        }

        $calc = $this->scoreSvc->calculate($unitId, $period, $userIdsForCalc, $professionId);
        $row = Arr::get($calc, 'users.' . (int) $target->id, null);
        $criteria = is_array($row['criteria'] ?? null) ? (array) $row['criteria'] : [];
        $sumWeight = (float) ($row['sum_weight'] ?? 0.0);

        $criteriaOut = [];
        foreach ($criteria as $c) {
            $included = (bool) ($c['included_in_wsm'] ?? false);
            $w = (float) ($c['weight'] ?? 0.0);
            $rel = (float) ($c['nilai_relativ_unit'] ?? 0.0);

            $criteriaOut[] = array_merge((array) $c, [
                'skor_tertimbang' => ($included && $sumWeight > 0.0) ? (($w * $rel) / $sumWeight) : 0.0,
            ]);
        }

        $assessment = PerformanceAssessment::query()
            ->where('assessment_period_id', (int) $period->id)
            ->where('user_id', (int) $target->id)
            ->with(['approvals.approver'])
            ->first();

        $latestApproval = null;
        if ($assessment && $assessment->approvals) {
            $a = $assessment->approvals
                ->whereNull('invalidated_at')
                ->sortByDesc(fn ($x) => ($x->acted_at?->timestamp ?? 0))
                ->first();
            if ($a) {
                $latestApproval = [
                    'level' => (int) ($a->level ?? 0),
                    'status' => (string) ($a->status?->value ?? $a->status ?? ''),
                    'approver_name' => (string) ($a->approver?->name ?? '-'),
                    'acted_at' => $a->acted_at,
                    'note' => (string) ($a->note ?? ''),
                ];
            }
        }

        $totalRel = $row['total_wsm_relative'] ?? ($row['total_wsm'] ?? null);
        $totalVal = $row['total_wsm_value'] ?? null;

        return [
            'calculation_source' => (string) ($calc['calculation_source'] ?? ($mode === 'snapshot' ? 'snapshot' : 'live')),
            'snapshotted_at' => $calc['snapshotted_at'] ?? null,
            'total_wsm_relative' => $totalRel === null ? null : (float) $totalRel,
            'total_wsm_value' => $totalVal === null ? null : (float) $totalVal,
            'sum_weight' => $sumWeight,
            'criteria' => $criteriaOut,
            'assessment' => $assessment,
            'latest_approval' => $latestApproval,
            'snapshot_detail_missing' => ($mode === 'snapshot' && empty($criteriaOut)),
        ];
    }
}
