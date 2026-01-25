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
use Illuminate\Support\Facades\DB;

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
            'assessment_period_id' => ['required_without_all:period_id', 'integer'],
            'period_id' => ['required_without_all:assessment_period_id', 'integer'],
            'rater_role' => ['required', 'in:pegawai_medis,kepala_unit,kepala_poliklinik'],
            'target_user_id' => 'required|integer',
            'unit_id' => 'nullable|integer',
            'score' => 'required|integer|min:1|max:100',
            'performance_criteria_id' => [$applyAll ? 'nullable' : 'required', 'integer'],
        ];
        $validated = $req->validate($rules);

        $raterId = Auth::id();
        $periodId = (int) ($validated['assessment_period_id'] ?? $validated['period_id'] ?? 0);
        $targetId = (int) $validated['target_user_id'];
        $score = (int) $validated['score'];
        $raterRole = (string) ($validated['rater_role'] ?? 'pegawai_medis');

        if ($periodId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Periode penilaian tidak valid.',
            ], 422);
        }

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

        // Prevent spoofing rater_role: ensure the current user has the claimed role.
        $currentUser = Auth::user();
        if ($raterRole === 'pegawai_medis' && !($currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('pegawai_medis'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Role penilai tidak valid untuk akun ini.',
            ], 422);
        }
        if ($raterRole === 'kepala_unit' && !($currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('kepala_unit'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Role penilai tidak valid untuk akun ini.',
            ], 422);
        }
        if ($raterRole === 'kepala_poliklinik' && !($currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('kepala_poliklinik'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Role penilai tidak valid untuk akun ini.',
            ], 422);
        }

        // Business rule: during REVISION, only heads can redo/adjust 360.
        if ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_REVISION && $raterRole === 'pegawai_medis') {
            return response()->json([
                'ok' => false,
                'message' => 'Penilaian 360 tidak dapat direvisi oleh pegawai. Revisi hanya untuk Kepala Unit / Kepala Poliklinik.',
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

        // Compatibility: earlier data/seeding may have stored head-role assessments using the user's base profession.
        // To ensure existing scores stay visible and aren't double-counted, we merge such legacy rows into
        // the resolved role profession context.
        $fallbackProfessionIds = collect([
            (int) $assessorProfessionId,
            (int) ($assessor->profession_id ?? 0),
        ])->filter(fn($id) => $id > 0)->unique()->values()->all();

        $assessorType = AssessorTypeResolver::resolve($assessor, $target, (int) $assessorProfessionId);

        // Special case:
        // When a unit head rates themselves, the relationship should contribute to the supervisor bucket
        // (atasan level 1) so the supervisor weight isn't treated as missing.
        if ($raterRole === 'kepala_unit' && (int) $targetId === (int) $raterId) {
            $assessorType = 'supervisor';
        }

        if (in_array($assessorType, ['peer', 'subordinate'], true)) {
            if ((int) ($assessor->unit_id ?? 0) !== (int) ($target->unit_id ?? 0)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Penilai tidak berada pada unit yang sama dengan pegawai yang dinilai.',
                ], 422);
            }
        }

        if (in_array($assessorType, ['supervisor', 'peer', 'subordinate'], true)) {
            $assesseeProfessionId = $target->profession_id ? (int) $target->profession_id : null;
            $assessorProfessionIdInt = $assessorProfessionId ? (int) $assessorProfessionId : null;
            if (!$assesseeProfessionId || !$assessorProfessionIdInt) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Relasi profesi penilai belum valid untuk penilaian 360 ini.',
                ], 422);
            }

            $hasRelation = DB::table('profession_reporting_lines')
                ->where('assessee_profession_id', $assesseeProfessionId)
                ->where('assessor_profession_id', $assessorProfessionIdInt)
                ->where('relation_type', $assessorType)
                ->where('is_active', true)
                ->exists();

            if (!$hasRelation) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Relasi profesi penilai tidak sesuai dengan aturan penilaian 360.',
                ], 422);
            }
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

        $candidateAssessments = MultiRaterAssessment::query()
            ->where('assessee_id', $targetId)
            ->where('assessor_id', $raterId)
            ->where('assessor_type', $assessorType)
            ->where('assessment_period_id', $periodId)
            ->where(function ($q) use ($fallbackProfessionIds) {
                $q->whereNull('assessor_profession_id');
                if (!empty($fallbackProfessionIds)) {
                    $q->orWhereIn('assessor_profession_id', $fallbackProfessionIds);
                }
            })
            ->orderByRaw('assessor_profession_id = ? DESC', [(int) $assessorProfessionId])
            ->orderByDesc('id')
            ->get();

        /** @var MultiRaterAssessment|null $assessment */
        $assessment = $candidateAssessments->first();
        if (!$assessment) {
            $assessment = MultiRaterAssessment::create([
                'assessee_id' => $targetId,
                'assessor_id' => $raterId,
                'assessor_profession_id' => (int) $assessorProfessionId,
                'assessor_type' => $assessorType,
                'assessment_period_id' => $periodId,
                'status' => 'invited',
                'submitted_at' => null,
            ]);
        } else {
            // Normalize profession context.
            if ((int) ($assessment->assessor_profession_id ?? 0) !== (int) $assessorProfessionId) {
                $assessment->assessor_profession_id = (int) $assessorProfessionId;
                $assessment->save();
            }

            // Merge legacy duplicates (if any): copy missing detail rows into the chosen assessment,
            // and cancel the duplicates to avoid double counting in final scores.
            if ($candidateAssessments->count() > 1) {
                $bestStatus = (string) ($assessment->status ?? 'invited');
                $bestSubmittedAt = $assessment->submitted_at;

                foreach ($candidateAssessments as $dup) {
                    if ((int) $dup->id === (int) $assessment->id) {
                        continue;
                    }

                    if ((string) ($dup->status ?? '') === 'submitted') {
                        $bestStatus = 'submitted';
                        if ($dup->submitted_at && (!$bestSubmittedAt || $dup->submitted_at > $bestSubmittedAt)) {
                            $bestSubmittedAt = $dup->submitted_at;
                        }
                    }

                    $dupDetails = MultiRaterAssessmentDetail::query()
                        ->where('multi_rater_assessment_id', $dup->id)
                        ->get(['performance_criteria_id', 'score']);

                    foreach ($dupDetails as $d) {
                        MultiRaterAssessmentDetail::firstOrCreate(
                            [
                                'multi_rater_assessment_id' => $assessment->id,
                                'performance_criteria_id' => (int) $d->performance_criteria_id,
                            ],
                            [
                                // Only set score when the row doesn't exist yet.
                                'score' => (int) $d->score,
                            ]
                        );
                    }

                    // Cancel the duplicate assessment to prevent double counting.
                    if ((string) ($dup->status ?? '') !== 'cancelled') {
                        $dup->status = 'cancelled';
                        $dup->save();
                    }
                }

                if ($bestStatus === 'submitted' && (string) ($assessment->status ?? '') !== 'submitted') {
                    $assessment->status = 'submitted';
                    $assessment->submitted_at = $bestSubmittedAt;
                    $assessment->save();
                }
            }
        }

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
