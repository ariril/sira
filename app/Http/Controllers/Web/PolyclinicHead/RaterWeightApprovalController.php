<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Models\Profession;
use App\Models\RaterWeight;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaterWeightApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'rejected', 'archived', 'all'])],
        ]);

        $filters['q'] = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'active', 'rejected', 'archived', 'all'], true)) {
            $status = 'pending';
        }

        $scopeUnitIds = $this->scopeUnitIds(Auth::user());

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();

        $professionIds = collect();
        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('users')) {
            $professionIds = DB::table('users')
                ->whereIn('unit_id', $scopeUnitIds)
                ->whereNotNull('profession_id')
                ->distinct()
                ->pluck('profession_id');
        }

        $professions = Profession::query()
            ->when($professionIds->isNotEmpty(), fn($q) => $q->whereIn('id', $professionIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $criteriaOptions = PerformanceCriteria::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        $baseQuery = RaterWeight::query()
            ->when($scopeUnitIds->isNotEmpty(), fn($q) => $q->whereIn('unit_id', $scopeUnitIds))
            ->when($professionIds->isNotEmpty(), fn($q) => $q->whereIn('assessee_profession_id', $professionIds))
            ->when(!empty($filters['assessment_period_id']), fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id']))
            ->when(!empty($filters['performance_criteria_id']), fn($q) => $q->where('performance_criteria_id', (int) $filters['performance_criteria_id']))
            ->when(!empty($filters['assessee_profession_id']), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $search = $filters['q'];
                $q->where(function ($w) use ($search) {
                    $w->whereHas('unit', fn($u) => $u->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('criteria', fn($c) => $c->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assesseeProfession', fn($p) => $p->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assessorProfession', fn($p) => $p->where('name', 'like', '%' . $search . '%'));
                });
            });

        $filteredQuery = clone $baseQuery;
        if ($status !== 'all') {
            $filteredQuery->where('status', $status);
        }

        $pendingUnitTotal = (clone $baseQuery)
            ->where('status', RaterWeightStatus::PENDING->value)
            ->distinct('unit_id')
            ->count('unit_id');

        $unitIdsSub = (clone $filteredQuery)->select('unit_id')->distinct()->toBase();
        $units = DB::table('units as u')
            ->joinSub($unitIdsSub, 'rw', fn($j) => $j->on('rw.unit_id', '=', 'u.id'))
            ->select('u.id', 'u.name')
            ->orderBy('u.name')
            ->paginate(10)
            ->withQueryString();

        $pageUnitIds = collect($units->items())->pluck('id')->all();

        $rows = collect();
        if (!empty($pageUnitIds)) {
            $rows = (clone $filteredQuery)
                ->with(['period:id,name', 'unit:id,name', 'criteria:id,name', 'assesseeProfession:id,name', 'assessorProfession:id,name', 'proposedBy:id,name', 'decidedBy:id,name'])
                ->whereIn('unit_id', $pageUnitIds)
                ->orderBy('unit_id')
                ->orderBy('assessment_period_id')
                ->orderBy('performance_criteria_id')
                ->orderBy('id')
                ->get();
        }

        $itemsByUnit = $rows->groupBy('unit_id');

        $pendingByUnit = collect();
        if (!empty($pageUnitIds)) {
            $pendingByUnit = (clone $baseQuery)
                ->where('status', RaterWeightStatus::PENDING->value)
                ->whereIn('unit_id', $pageUnitIds)
                ->selectRaw('unit_id, COUNT(*) as c')
                ->groupBy('unit_id')
                ->pluck('c', 'unit_id');
        }

        return view('kepala_poli.rater_weights.index', [
            'units' => $units,
            'itemsByUnit' => $itemsByUnit,
            'pendingByUnit' => $pendingByUnit,
            'filters' => [
                ...$filters,
                'status' => $status,
            ],
            'periods' => $periods,
            'criteriaOptions' => $criteriaOptions,
            'professions' => $professions,
            'pendingUnitTotal' => $pendingUnitTotal,
        ]);
    }

    public function approve(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        $period = AssessmentPeriod::query()->find((int) ($raterWeight->assessment_period_id ?? 0));
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Approve Bobot Penilai 360');

        DB::transaction(function () use ($raterWeight) {
            $this->archiveActiveFor($raterWeight);

            $raterWeight->status = RaterWeightStatus::ACTIVE;
            $raterWeight->decided_by = auth()->id();
            $raterWeight->decided_at = now();
            $raterWeight->decided_note = null;
            $raterWeight->save();
        });

        return back()->with('status', 'Bobot penilai 360 disetujui dan diaktifkan.');
    }

    public function approveUnit(Request $request, int $unitId): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
        ]);

        $filters = [
            'q' => trim((string) ($data['q'] ?? '')),
            'assessment_period_id' => $data['assessment_period_id'] ?? null,
            'performance_criteria_id' => $data['performance_criteria_id'] ?? null,
            'assessee_profession_id' => $data['assessee_profession_id'] ?? null,
        ];

        $scopeUnitIds = $this->scopeUnitIds(Auth::user());
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($unitId)) {
            abort(403);
        }

        $pendingItems = $this->pendingQueryForUnit($unitId, $filters)->get();

        if ($pendingItems->isEmpty()) {
            return back()->with('status', 'Tidak ada bobot pending untuk disetujui pada unit ini.');
        }

        $periodIds = $pendingItems->pluck('assessment_period_id')->filter()->unique()->values();
        foreach ($periodIds as $pid) {
            $period = AssessmentPeriod::query()->find((int) $pid);
            AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Approve Bobot Penilai 360');
        }

        DB::transaction(function () use ($pendingItems) {
            foreach ($pendingItems as $item) {
                $this->archiveActiveFor($item);

                $item->status = RaterWeightStatus::ACTIVE;
                $item->decided_by = auth()->id();
                $item->decided_at = now();
                $item->decided_note = null;
                $item->save();
            }
        });

        return back()->with('status', $pendingItems->count() . ' bobot penilai 360 disetujui untuk unit ini.');
    }

    public function reject(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        $period = AssessmentPeriod::query()->find((int) ($raterWeight->assessment_period_id ?? 0));
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Reject Bobot Penilai 360');

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $raterWeight->status = RaterWeightStatus::REJECTED;
        $raterWeight->decided_by = auth()->id();
        $raterWeight->decided_at = now();
        $raterWeight->decided_note = $data['comment'];
        $raterWeight->save();

        return back()->with('status', 'Bobot penilai 360 ditolak.');
    }

    public function rejectUnit(Request $request, int $unitId): RedirectResponse
    {
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
            'q' => ['nullable', 'string', 'max:100'],
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
        ]);

        $filters = [
            'q' => trim((string) ($data['q'] ?? '')),
            'assessment_period_id' => $data['assessment_period_id'] ?? null,
            'performance_criteria_id' => $data['performance_criteria_id'] ?? null,
            'assessee_profession_id' => $data['assessee_profession_id'] ?? null,
        ];

        $scopeUnitIds = $this->scopeUnitIds(Auth::user());
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($unitId)) {
            abort(403);
        }

        $pendingQuery = $this->pendingQueryForUnit($unitId, $filters);
        $count = (clone $pendingQuery)->count();

        if ($count === 0) {
            return back()->with('status', 'Tidak ada bobot pending untuk ditolak pada unit ini.');
        }

        $periodIds = (clone $pendingQuery)->select('assessment_period_id')->distinct()->pluck('assessment_period_id')->filter()->values();
        foreach ($periodIds as $pid) {
            $period = AssessmentPeriod::query()->find((int) $pid);
            AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Reject Bobot Penilai 360');
        }

        DB::transaction(function () use ($pendingQuery, $data) {
            $pendingQuery->update([
                'status' => RaterWeightStatus::REJECTED->value,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
                'decided_note' => $data['comment'],
            ]);
        });

        return back()->with('status', $count . ' bobot penilai 360 ditolak untuk unit ini.');
    }

    private function scopeUnitIds($user)
    {
        $scopeUnitIds = collect();

        if (Schema::hasTable('units')) {
            if ($user?->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $user->unit_id)->pluck('id');
            }

            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        return $scopeUnitIds;
    }

    private function archiveActiveFor(RaterWeight $raterWeight): void
    {
        RaterWeight::query()
            ->where('assessment_period_id', $raterWeight->assessment_period_id)
            ->where('unit_id', $raterWeight->unit_id)
            ->where('performance_criteria_id', $raterWeight->performance_criteria_id)
            ->where('assessee_profession_id', $raterWeight->assessee_profession_id)
            ->where('assessor_type', $raterWeight->assessor_type)
            ->when(
                $raterWeight->assessor_profession_id === null,
                fn($q) => $q->whereNull('assessor_profession_id'),
                fn($q) => $q->where('assessor_profession_id', $raterWeight->assessor_profession_id)
            )
            ->when(
                $raterWeight->assessor_level === null,
                fn($q) => $q->whereNull('assessor_level'),
                fn($q) => $q->where('assessor_level', $raterWeight->assessor_level)
            )
            ->where('status', RaterWeightStatus::ACTIVE->value)
            ->update([
                'status' => RaterWeightStatus::ARCHIVED->value,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);
    }

    private function pendingQueryForUnit(int $unitId, array $filters)
    {
        return RaterWeight::query()
            ->where('unit_id', $unitId)
            ->where('status', RaterWeightStatus::PENDING->value)
            ->when(!empty($filters['assessment_period_id']), fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id']))
            ->when(!empty($filters['performance_criteria_id']), fn($q) => $q->where('performance_criteria_id', (int) $filters['performance_criteria_id']))
            ->when(!empty($filters['assessee_profession_id']), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']))
            ->when(!empty($filters['q']), function ($q) use ($filters) {
                $search = $filters['q'];
                $q->where(function ($w) use ($search) {
                    $w->whereHas('criteria', fn($c) => $c->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assesseeProfession', fn($p) => $p->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assessorProfession', fn($p) => $p->where('name', 'like', '%' . $search . '%'));
                });
            });
    }
}
