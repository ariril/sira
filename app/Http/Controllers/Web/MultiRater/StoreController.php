<?php

namespace App\Http\Controllers\Web\MultiRater;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\CriteriaRaterRule;
use App\Models\User;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Services\MultiRater\CriteriaResolver;
use App\Services\MultiRater\AssessorTypeResolver;
use App\Services\MultiRater\AssessorProfessionResolver;
use App\Support\AssessmentPeriodGuard;

class StoreController extends Controller
{
    public function store(Request $req, PeriodPerformanceAssessmentService $perfSvc)
    {
        // Browser locale can format number inputs with comma decimals (e.g. 99,00 / 99.00).
        // Keep persistence consistent: score is stored & validated as INTEGER (1..100).
        // So we normalize any numeric-like value to an integer string BEFORE validation.
        if ($req->has('score')) {
            $raw = trim((string) $req->input('score'));
            $raw = str_replace(',', '.', $raw);
            if ($raw !== '' && is_numeric($raw)) {
                $req->merge([
                    'score' => (string) ((int) round((float) $raw)),
                ]);
            }
        }

        $applyAll = $req->boolean('apply_all');
        $rules = [
            'assessment_period_id' => ['required_without:period_id', 'integer'],
            'period_id' => ['required_without:assessment_period_id', 'integer'],
            'rater_role' => ['required', 'in:pegawai_medis,kepala_unit,kepala_poliklinik'],
            'target_user_id' => 'required|integer',
            'unit_id' => 'nullable|integer',
            'score' => 'required|integer|min:1|max:100',
            'performance_criteria_id' => [$applyAll ? 'nullable' : 'required', 'integer'],
        ];
        $validated = $req->validate($rules);

        $raterId = Auth::id();
        $periodId = (int) ($validated['assessment_period_id'] ?? $validated['period_id']);
        $targetId = (int) $validated['target_user_id'];
        $score = (int) $validated['score'];
        $raterRole = (string) ($validated['rater_role'] ?? 'pegawai_medis');

        $period = AssessmentPeriod::query()->find($periodId);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');
        try {
            AssessmentPeriodGuard::requireActiveOrRevision($period, 'Penilaian 360');
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $target = User::query()
            ->with('profession:id,code')
            ->select('id', 'unit_id', 'profession_id', 'name')
            ->findOrFail($targetId);

        $assessor = User::query()
            ->with('profession:id,code')
            ->select('id', 'unit_id', 'profession_id')
            ->findOrFail($raterId);

        $assessorProfessionId = AssessorProfessionResolver::resolve($assessor, $raterRole);
        if (!$assessorProfessionId) {
            return response()->json([
                'ok' => false,
                'message' => 'Profesi penilai belum diatur. Hubungi admin untuk melengkapi data profesi.',
            ], 422);
        }

        $assessorType = AssessorTypeResolver::resolve($assessor, $target, (int) $assessorProfessionId);

        // Special case:
        // When a unit head rates themselves, the relationship should contribute to the supervisor bucket
        // (atasan level 1) so the supervisor weight isn't treated as missing.
        if ($raterRole === 'kepala_unit' && (int) $targetId === (int) $raterId) {
            $assessorType = 'supervisor';
        }
        $unitId = $validated['unit_id'] ?? $target->unit_id;

        $window = Assessment360Window::where('assessment_period_id', $periodId)
            ->where('is_active', true)
            ->first();

        // During ACTIVE: strictly require an active window and date range.
        // During REVISION: allow fixes even if the window is already closed.
        if ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
            $start = optional($window?->start_date)?->copy()->startOfDay();
            $end = optional($window?->end_date)?->copy()->endOfDay();
            if (!$window || !$start || !$end || now()->lt($start) || now()->gt($end)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Periode penilaian sudah ditutup.',
                ], 422);
            }
        } else {
            $anyWindow = Assessment360Window::where('assessment_period_id', $periodId)->exists();
            if (!$anyWindow) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Jadwal penilaian 360 belum dibuka untuk periode ini.',
                ], 422);
            }
        }

        $criteriaOptions = CriteriaResolver::forUnit($unitId, $periodId);
        $criteriaIds = $criteriaOptions->pluck('id')->filter()->map(fn($id) => (int) $id);

        if ($criteriaIds->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Belum ada kriteria aktif untuk unit ini.',
            ], 422);
        }

        /** @var Collection<int,int> $targetsToApply */
        $targetCriteria = $criteriaIds;
        if (!$applyAll) {
            $criteriaId = (int) ($validated['performance_criteria_id'] ?? 0);
            if (!$criteriaIds->contains($criteriaId)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Kriteria tidak tersedia untuk unit ini.',
                ], 422);
            }
            $targetCriteria = collect([$criteriaId]);
        }

        // Apply criteria_rater_rules if present for this assessor type.
        $allowedCriteriaIds = $this->filterCriteriaByAssessorType($targetCriteria, $assessorType);
        if ($allowedCriteriaIds->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Kriteria tidak tersedia untuk tipe penilai ini.',
            ], 422);
        }

        $assessment = MultiRaterAssessment::firstOrCreate(
            [
                'assessee_id' => $targetId,
                'assessor_id' => $raterId,
                'assessor_profession_id' => (int) $assessorProfessionId,
                'assessor_type' => $assessorType,
                'assessment_period_id' => $periodId,
            ],
            [
                'status' => 'invited',
                'submitted_at' => null,
            ]
        );

        if (in_array($assessment->status, ['cancelled'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Undangan penilaian sudah dibatalkan.',
            ], 422);
        }

        if ($assessment->status === 'invited') {
            $assessment->status = 'in_progress';
            $assessment->submitted_at = null;
            $assessment->save();
        }

        foreach ($allowedCriteriaIds as $criteriaId) {
            MultiRaterAssessmentDetail::updateOrCreate(
                [
                    'multi_rater_assessment_id' => $assessment->id,
                    'performance_criteria_id' => $criteriaId,
                ],
                [
                    'score' => $score,
                ]
            );
        }

        // Update Penilaian Saya for the assessee group (supports locked periods too).
        $perfSvc->recalculateForGroup(
            $periodId,
            $target->unit_id ? (int) $target->unit_id : null,
            $target->profession_id ? (int) $target->profession_id : null
        );

        $completed = MultiRaterAssessmentDetail::query()
            ->where('multi_rater_assessment_id', $assessment->id)
            ->pluck('performance_criteria_id')
            ->filter()
            ->map(fn($id) => (int) $id);

        // Pending should be based on eligible criteria for this assessor type (not the full unit criteria list).
        $eligibleCriteriaIds = CriteriaResolver::filterCriteriaIdsByAssessorType($criteriaIds, $assessorType);
        $pending = $eligibleCriteriaIds->diff($completed)->values();

        // IMPORTANT:
        // Keep status IN_PROGRESS while the 360 window is open.
        // Finalization to SUBMITTED is handled after the window/period ends.
        if ($assessment->status !== 'in_progress') {
            $assessment->status = 'in_progress';
            $assessment->submitted_at = null;
            $assessment->save();
        }

        return response()->json([
            'ok' => true,
            'pending' => $pending,
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
            ],
            'filled' => $allowedCriteriaIds->values(),
        ]);
    }

    private function filterCriteriaByAssessorType(Collection $candidateCriteriaIds, string $assessorType): Collection
    {
        $candidateCriteriaIds = $candidateCriteriaIds->filter()->map(fn($id) => (int) $id)->values();
        if ($candidateCriteriaIds->isEmpty()) {
            return $candidateCriteriaIds;
        }

        $hasRules = CriteriaRaterRule::query()->whereIn('performance_criteria_id', $candidateCriteriaIds)->exists();
        if (!$hasRules) {
            return $candidateCriteriaIds;
        }

        $allowed = CriteriaRaterRule::query()
            ->whereIn('performance_criteria_id', $candidateCriteriaIds)
            ->where('assessor_type', $assessorType)
            ->pluck('performance_criteria_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $allowedSet = array_flip($allowed);
        return $candidateCriteriaIds->filter(fn($id) => isset($allowedSet[(int) $id]))->values();
    }
}
