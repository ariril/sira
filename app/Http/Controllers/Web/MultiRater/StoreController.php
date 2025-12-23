<?php

namespace App\Http\Controllers\Web\MultiRater;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use App\Models\Assessment360Window;
use App\Models\MultiRaterScore;
use App\Models\User;
use App\Services\PeriodPerformanceAssessmentService;
use App\Services\MultiRater\CriteriaResolver;

class StoreController extends Controller
{
    public function store(Request $req, PeriodPerformanceAssessmentService $perfSvc)
    {
        $applyAll = $req->boolean('apply_all');
        $rules = [
            'period_id' => 'required|integer',
            'target_user_id' => 'required|integer',
            'unit_id' => 'nullable|integer',
            'score' => 'required|integer|min:1|max:100',
            'performance_criteria_id' => [$applyAll ? 'nullable' : 'required', 'integer'],
        ];
        $validated = $req->validate($rules);

        $raterId = Auth::id();
        $periodId = (int) $validated['period_id'];
        $targetId = (int) $validated['target_user_id'];
        $score = (int) $validated['score'];

        if ($targetId === $raterId) {
            return response()->json(['ok' => false, 'message' => 'Tidak bisa menilai diri sendiri.'], 400);
        }

        $target = User::select('id', 'unit_id', 'profession_id', 'name')->findOrFail($targetId);
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

        foreach ($targetCriteria as $criteriaId) {
            $this->persistScore($periodId, $raterId, $targetId, $criteriaId, $score);
        }

        // Update Penilaian Saya for the assessee group (supports locked periods too).
        $perfSvc->recalculateForGroup(
            $periodId,
            $target->unit_id ? (int) $target->unit_id : null,
            $target->profession_id ? (int) $target->profession_id : null
        );

        $completed = MultiRaterScore::query()
            ->where('period_id', $periodId)
            ->where('rater_user_id', $raterId)
            ->where('target_user_id', $targetId)
            ->pluck('performance_criteria_id')
            ->filter()
            ->map(fn($id) => (int) $id);

        $pending = $criteriaIds->diff($completed)->values();

        return response()->json([
            'ok' => true,
            'pending' => $pending,
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
            ],
            'filled' => $targetCriteria->values(),
        ]);
    }

    private function persistScore(int $periodId, int $raterId, int $targetId, int $criteriaId, int $score): void
    {
        MultiRaterScore::updateOrCreate(
            [
                'period_id' => $periodId,
                'rater_user_id' => $raterId,
                'target_user_id' => $targetId,
                'performance_criteria_id' => $criteriaId,
            ],
            ['score' => $score]
        );
    }
}
