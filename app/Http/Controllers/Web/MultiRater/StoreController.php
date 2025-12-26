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
use App\Services\PeriodPerformanceAssessmentService;
use App\Services\MultiRater\CriteriaResolver;
use App\Services\MultiRater\AssessorTypeResolver;

class StoreController extends Controller
{
    public function store(Request $req, PeriodPerformanceAssessmentService $perfSvc)
    {
        $applyAll = $req->boolean('apply_all');
        $rules = [
            'assessment_period_id' => ['required_without:period_id', 'integer'],
            'period_id' => ['required_without:assessment_period_id', 'integer'],
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

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period || $period->status !== AssessmentPeriod::STATUS_ACTIVE) {
            return response()->json([
                'ok' => false,
                'message' => 'Penilaian 360 hanya dapat dilakukan ketika periode ACTIVE.',
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

        $assessorType = AssessorTypeResolver::resolve($assessor, $target);
        $unitId = $validated['unit_id'] ?? $target->unit_id;

        $window = Assessment360Window::where('assessment_period_id', $periodId)
            ->where('is_active', true)
            ->first();
        $start = optional($window?->start_date)?->copy()->startOfDay();
        $end = optional($window?->end_date)?->copy()->endOfDay();
        if (!$window || !$start || !$end || now()->lt($start) || now()->gt($end)) {
            return response()->json([
                'ok' => false,
                'message' => 'Periode penilaian sudah ditutup.',
            ], 422);
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
                'assessor_type' => $assessorType,
                'assessment_period_id' => $periodId,
            ],
            [
                'status' => 'in_progress',
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

        $pending = $criteriaIds->diff($completed)->values();

        // Auto-submit when all criteria are filled for this target
        if ($pending->isEmpty() && $assessment->status !== 'submitted') {
            $assessment->status = 'submitted';
            $assessment->submitted_at = now();
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
