<?php

namespace App\Services\Remuneration;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Remuneration;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\User;
use App\Support\AttachedRemunerationCalculator;
use App\Support\ProportionalAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemunerationCalculationService
{
    private const DISTRIBUTION_MODE_TOTAL_UNIT = 'total_unit_proportional';
    private const DISTRIBUTION_MODE_ATTACHED = 'attached_per_employee';

    /**
     * @return array{ok:bool,message?:string,skipped?:bool}
     */
    public function runForPeriod(AssessmentPeriod $period, ?int $actorId = null, bool $skipIfAlreadyCalculated = true, bool $forceZero = false): array
    {
        $periodId = (int) $period->id;
        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Periode tidak valid untuk perhitungan remunerasi.'];
        }

        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            return ['ok' => false, 'message' => 'Perhitungan tidak dapat dijalankan: periode sedang DITOLAK (approval rejected).'];
        }

        if ($skipIfAlreadyCalculated && !$forceZero) {
            $alreadyCalculated = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->whereNotNull('calculated_at')
                ->exists();
            if ($alreadyCalculated) {
                return ['ok' => true, 'skipped' => true, 'message' => 'Perhitungan dilewati: remunerasi sudah pernah dihitung.'];
            }
        }

        // Hard gate: do not allow calculation while period is ACTIVE/DRAFT
        $isLocked = in_array($period->status, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true);
        if (!$isLocked && !$forceZero) {
            return ['ok' => false, 'message' => 'Perhitungan hanya bisa dijalankan setelah periode ditutup (LOCKED).'];
        }

        // Hard gate: all assessments must be finally validated
        $assessmentCount = (int) PerformanceAssessment::query()->where('assessment_period_id', $periodId)->count();
        $wsmFilledCount = (int) PerformanceAssessment::query()
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('total_wsm_score')
            ->count();
        $validatedCount = (int) PerformanceAssessment::query()
            ->where('assessment_period_id', $periodId)
            ->where('validation_status', AssessmentValidationStatus::VALIDATED->value)
            ->count();
        if (($assessmentCount === 0 || $assessmentCount !== $validatedCount) && !$forceZero) {
            return ['ok' => false, 'message' => 'Tidak dapat menjalankan perhitungan: masih ada penilaian yang belum tervalidasi final.'];
        }

        if (($wsmFilledCount !== $assessmentCount) && !$forceZero) {
            return ['ok' => false, 'message' => 'Tidak dapat menjalankan perhitungan: skor kinerja belum siap (skor kinerja masih kosong). Pastikan bobot kriteria ACTIVE sudah tersedia, lalu hitung ulang penilaian kinerja.'];
        }

        // If any allocation for this period uses ATTACHED mode, require VALUE WSM to be filled too.
        $allocationsForModeCheck = Allocation::query()
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('published_at')
            ->get(['unit_id', 'profession_id']);

        $needsValueWsm = false;
        foreach ($allocationsForModeCheck as $a) {
            if ($this->resolveDistributionMode($periodId, (int) $a->unit_id, $period, $a->profession_id === null ? null : (int) $a->profession_id) === self::DISTRIBUTION_MODE_ATTACHED) {
                $needsValueWsm = true;
                break;
            }
        }

        if ($needsValueWsm && !$forceZero) {
            $wsmValueFilledCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->whereNotNull('total_wsm_value_score')
                ->count();

            if ($wsmValueFilledCount !== $assessmentCount) {
                return ['ok' => false, 'message' => 'Tidak dapat menjalankan perhitungan: nilai kinerja belum siap (nilai kinerja masih kosong). Jalankan hitung ulang penilaian kinerja setelah update sistem.'];
            }
        }

        // Hard gate: all allocations must be published (not partial)
        $allocTotal = (int) Allocation::query()->where('assessment_period_id', $periodId)->count();
        $allocPublished = (int) Allocation::query()->where('assessment_period_id', $periodId)->whereNotNull('published_at')->count();
        if (($allocTotal === 0 || $allocTotal !== $allocPublished) && !$forceZero) {
            return ['ok' => false, 'message' => 'Tidak dapat menjalankan perhitungan: alokasi remunerasi unit belum dipublish semua.'];
        }

        $allocations = Allocation::with(['unit:id,name'])
            ->where('assessment_period_id', $periodId)
            ->when(!$forceZero, fn ($q) => $q->whereNotNull('published_at'))
            ->get();

        if ($allocations->isEmpty()) {
            return ['ok' => true, 'message' => 'Tidak ada alokasi remunerasi untuk periode ini.'];
        }

        $skipped = [];
        DB::transaction(function () use ($allocations, $period, $periodId, $actorId, $forceZero, &$skipped) {
            foreach ($allocations as $alloc) {
                $overrideAmount = $forceZero && empty($alloc->published_at)
                    ? 0.0
                    : (float) $alloc->amount;
                $res = $this->distributeAllocation(
                    $alloc,
                    $period,
                    $periodId,
                    $alloc->profession_id,
                    $overrideAmount,
                    $actorId,
                    $forceZero
                );

                if (!($res['ok'] ?? true)) {
                    $skipped[] = (string) ($res['message'] ?? 'Alokasi dilewati.');
                }
            }
        });

        if (!empty($skipped) && !$forceZero) {
            $msg = "Perhitungan selesai, tetapi ada alokasi yang dilewati karena data kinerja grup tidak valid.\n- " . implode("\n- ", array_slice($skipped, 0, 10));
            if (count($skipped) > 10) {
                $msg .= "\n(dan " . (count($skipped) - 10) . " lainnya)";
            }
            return ['ok' => false, 'message' => $msg];
        }

        return ['ok' => true, 'message' => $forceZero
            ? 'Perhitungan paksa selesai (nilai 0 untuk skor kinerja kosong & alokasi belum dipublikasikan).'
            : 'Perhitungan selesai (berdasarkan skor kinerja terkonfigurasi).'];
    }

    /**
     * Decide how remuneration should be distributed for a unit+period.
     */
    private function resolveDistributionMode(int $periodId, int $unitId, ?AssessmentPeriod $period = null, ?int $professionId = null): string
    {
        $periodId = (int) $periodId;
        $unitId = (int) $unitId;
        if ($periodId <= 0 || $unitId <= 0) {
            return self::DISTRIBUTION_MODE_TOTAL_UNIT;
        }

        if ($period && $period->isFrozen()) {
            $snapshotMode = $this->tryResolveDistributionModeFromSnapshot($period, $unitId, $professionId);
            if ($snapshotMode !== null) {
                return $snapshotMode;
            }
        }

        $bases = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.status', 'active')
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.normalization_basis')
            ->map(fn ($v) => (string) ($v ?? 'total_unit'))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();

        if (empty($bases)) {
            return self::DISTRIBUTION_MODE_TOTAL_UNIT;
        }

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @param array<int,string> $bases
     */
    private function determineDistributionModeFromBases(array $bases): string
    {
        foreach ($bases as $b) {
            if (in_array((string) $b, ['average_unit', 'max_unit', 'custom_target'], true)) {
                return self::DISTRIBUTION_MODE_ATTACHED;
            }
        }
        return self::DISTRIBUTION_MODE_TOTAL_UNIT;
    }

    private function tryResolveDistributionModeFromSnapshot(AssessmentPeriod $period, int $unitId, ?int $professionId): ?string
    {
        if (!$period->isFrozen()) {
            return null;
        }

        if (!Schema::hasTable('assessment_period_user_membership_snapshots')) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $periodId = (int) $period->id;
        if ($periodId <= 0 || $unitId <= 0) {
            return null;
        }

        $q = DB::table('assessment_period_user_membership_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', (int) $unitId);

        if ($professionId !== null) {
            $q->where('profession_id', (int) $professionId);
        }

        $sampleUserId = $q->orderBy('user_id')->value('user_id');
        if (!$sampleUserId) {
            return null;
        }

        $payload = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('user_id', (int) $sampleUserId)
            ->value('payload');

        if ($payload === null) {
            return null;
        }

        $decoded = is_string($payload) ? json_decode($payload, true) : (array) $payload;
        if (!is_array($decoded)) {
            return null;
        }

        $calc = (array) ($decoded['calc'] ?? []);
        $bases = array_values((array) ($calc['basis_by_criteria'] ?? []));
        $bases = array_values(array_filter(array_map(fn ($v) => (string) ($v ?? ''), $bases), fn ($v) => $v !== ''));
        if (empty($bases)) {
            return null;
        }

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @return array<int>
     */
    private function resolveRecipientUserIds(AssessmentPeriod $period, int $periodId, int $unitId, ?int $professionId): array
    {
        if ($period->isFrozen() && Schema::hasTable('assessment_period_user_membership_snapshots')) {
            $q = DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', (int) $periodId)
                ->where('unit_id', (int) $unitId);

            if ($professionId !== null) {
                $q->where('profession_id', (int) $professionId);
            }

            return $q->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        }

        return User::query()
            ->where('unit_id', (int) $unitId)
            ->when($professionId !== null, fn ($q) => $q->where('profession_id', (int) $professionId))
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param array<int> $userIds
     * @return array{relative: array<int,?float>, value: array<int,?float>}|null
     */
    private function tryLoadSnapshotWsmScores(int $periodId, array $userIds): ?array
    {
        if ($periodId <= 0 || empty($userIds)) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $rows = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', (int) $periodId)
            ->whereIn('user_id', array_map('intval', $userIds))
            ->get(['user_id', 'payload']);

        if ($rows->isEmpty()) {
            return null;
        }

        $relative = [];
        $value = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $payload = is_string($r->payload) ? json_decode($r->payload, true) : (array) $r->payload;
            if (!is_array($payload)) {
                continue;
            }

            $calc = (array) ($payload['calc'] ?? []);
            $rel = $calc['total_wsm_relative'] ?? null;
            $val = $calc['total_wsm_value'] ?? null;

            if ($rel === null) {
                $rel = $calc['user']['total_wsm_relative'] ?? ($calc['user']['total_wsm'] ?? null);
            }
            if ($val === null) {
                $val = $calc['user']['total_wsm_value'] ?? null;
            }

            $relative[$uid] = $rel === null ? null : (float) $rel;
            $value[$uid] = $val === null ? null : (float) $val;
        }

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if (!array_key_exists($uid, $relative) || !array_key_exists($uid, $value)) {
                return null;
            }
        }

        return ['relative' => $relative, 'value' => $value];
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function distributeAllocation(Allocation $alloc, $period, int $periodId, ?int $professionId = null, ?float $overrideAmount = null, ?int $actorId = null, bool $forceZero = false): array
    {
        if (!($period instanceof AssessmentPeriod)) {
            $period = AssessmentPeriod::findOrFail($periodId);
        }

        $userIds = $this->resolveRecipientUserIds($period, $periodId, (int) $alloc->unit_id, $professionId);
        if (empty($userIds)) {
            return ['ok' => true];
        }

        $amount = $overrideAmount ?? (float) $alloc->amount;
        if ($amount <= 0 && !$forceZero) {
            return ['ok' => true];
        }

        $wsmRelativeByUserId = [];
        $wsmValueByUserId = [];

        $snapWsm = $period->isFrozen() ? $this->tryLoadSnapshotWsmScores($periodId, $userIds) : null;
        if ($snapWsm !== null) {
            $wsmRelativeByUserId = (array) ($snapWsm['relative'] ?? []);
            $wsmValueByUserId = (array) ($snapWsm['value'] ?? []);
        } else {
            $wsmRows = DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->whereIn('user_id', $userIds)
                ->get(['user_id', 'total_wsm_score', 'total_wsm_value_score']);

            foreach ($wsmRows as $r) {
                $uid = (int) $r->user_id;
                $wsmRelativeByUserId[$uid] = $r->total_wsm_score === null ? null : (float) $r->total_wsm_score;
                $wsmValueByUserId[$uid] = $r->total_wsm_value_score === null ? null : (float) $r->total_wsm_value_score;
            }
        }

        $unitTotal = 0.0;
        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            $unitTotal += (float) (($wsmRelativeByUserId[$uid] ?? null) ?? 0.0);
        }
        $mode = $this->resolveDistributionMode($periodId, (int) $alloc->unit_id, $period, $professionId);

        if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT && $unitTotal <= 0 && !$forceZero) {
            $unitName = $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id);
            $profLabel = $professionId ? ('Profesi#' . (string) $professionId) : 'Semua profesi';
            return [
                'ok' => false,
                'message' => "{$unitName} ({$profLabel}): Total WSM grup = 0. Pastikan bobot kriteria ACTIVE tersedia dan total_wsm_score sudah terhitung.",
            ];
        }

        $weightsByUserId = [];
        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            $weightsByUserId[$uid] = (float) (($wsmRelativeByUserId[$uid] ?? null) ?? 0.0);
        }

        if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT) {
            $allocatedAmounts = $unitTotal > 0
                ? ProportionalAllocator::allocate((float) $amount, $weightsByUserId)
                : array_fill_keys($userIds, 0.0);
            $attachedMeta = null;
        } else {
            $payoutPctByUser = [];
            $missingValue = 0;
            foreach ($userIds as $userId) {
                $uid = (int) $userId;
                if (!array_key_exists($uid, $wsmValueByUserId) || $wsmValueByUserId[$uid] === null) {
                    $missingValue++;
                    $payoutPctByUser[$uid] = 0.0;
                } else {
                    $payoutPctByUser[$uid] = (float) $wsmValueByUserId[$uid];
                }
            }

            if ($missingValue > 0 && !$forceZero) {
                $unitName = $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id);
                $profLabel = $professionId ? ('Profesi#' . (string) $professionId) : 'Semua profesi';
                return [
                    'ok' => false,
                    'message' => "{$unitName} ({$profLabel}): total_wsm_value_score belum terisi untuk {$missingValue} pegawai. Jalankan recalculate penilaian (WSM) sebelum kalkulasi remunerasi.",
                ];
            }

            $attachedMeta = AttachedRemunerationCalculator::calculate((float) $amount, $payoutPctByUser, 2);
            $allocatedAmounts = (array) ($attachedMeta['amounts'] ?? []);
        }

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $userScoreRelative = (float) (($wsmRelativeByUserId[$userId] ?? null) ?? 0.0);
            $userScoreValue = (float) (($wsmValueByUserId[$userId] ?? null) ?? 0.0);

            $sharePct = $unitTotal > 0 ? ($userScoreRelative / $unitTotal) : (1 / max(count($userIds), 1));
            $final = (float) ($allocatedAmounts[$userId] ?? 0.0);

            $rem = Remuneration::firstOrNew([
                'user_id' => $userId,
                'assessment_period_id' => $periodId,
            ]);

            if (!empty($rem->published_at)) {
                continue;
            }

            $rem->amount = $final;
            $rem->calculated_at = now();
            if ($actorId) {
                $rem->revised_by = $actorId;
            }

            $allocationDetails = [
                'unit_id' => $alloc->unit_id,
                'unit_name' => $alloc->unit->name ?? null,
                'profession_id' => $professionId,
                'published_amount' => (float) $alloc->amount,
                'line_amount' => $amount,
                'recipients_source' => $period->isFrozen() && Schema::hasTable('assessment_period_user_membership_snapshots') ? 'snapshot_membership' : 'live_users',
                'wsm_source' => $period->isFrozen() && $snapWsm !== null ? 'snapshot' : 'performance_assessments',
                'unit_total_wsm' => $unitTotal,
                'user_wsm_score' => $userScoreRelative,
                'unit_total_wsm_relative' => $unitTotal,
                'user_wsm_score_relative' => $userScoreRelative,
                'user_wsm_score_value' => $userScoreValue,
            ];

            if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT) {
                $allocationDetails += [
                    'distribution_mode' => self::DISTRIBUTION_MODE_TOTAL_UNIT,
                    'share_percent' => round($sharePct * 100, 6),
                    'rounding' => [
                        'method' => 'largest_remainder_cents',
                        'precision' => 2,
                    ],
                ];
            } else {
                $remunMax = (float) (($attachedMeta['remuneration_max_per_employee'] ?? 0.0) ?: 0.0);
                $leftover = (float) (($attachedMeta['leftover_amount'] ?? 0.0) ?: 0.0);
                $headcount = (int) (($attachedMeta['headcount'] ?? 0) ?: 0);

                $payoutPct = max(0.0, min(100.0, (float) $userScoreValue));

                $allocationDetails += [
                    'distribution_mode' => self::DISTRIBUTION_MODE_ATTACHED,
                    'headcount' => $headcount,
                    'remuneration_max_per_employee' => $remunMax,
                    'payout_percent' => round($payoutPct, 6),
                    'amount_final' => round($final, 2),
                    'leftover_amount' => round($leftover, 2),
                    'share_percent' => round($payoutPct, 6),
                    'rounding' => [
                        'method' => 'round',
                        'precision' => 2,
                        'note' => 'No remainder redistribution (leftover kept)',
                    ],
                ];
            }

            $rem->calculation_details = [
                'method'    => $mode === self::DISTRIBUTION_MODE_TOTAL_UNIT
                    ? 'unit_profession_wsm_proportional'
                    : 'unit_profession_attached_percent',
                'period_id' => $periodId,
                'generated' => now()->toDateTimeString(),
                'allocation' => $allocationDetails,
                'wsm' => [
                    'user_total' => $userScoreRelative,
                    'unit_total' => $unitTotal,
                    'source' => 'performance_assessments.total_wsm_score',
                    'user_total_relative' => $userScoreRelative,
                    'user_total_value' => $userScoreValue,
                    'source_value' => 'performance_assessments.total_wsm_value_score',
                ],
                'komponen' => [
                    'dasar' => 0,
                    'pasien_ditangani' => [
                        'jumlah' => null,
                        'nilai' => $final,
                    ],
                    'review_pelanggan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                    'kontribusi_tambahan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                ],
            ];
            $rem->save();
        }

        return ['ok' => true];
    }
}
