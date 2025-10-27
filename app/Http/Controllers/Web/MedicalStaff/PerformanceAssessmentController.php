<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\PerformanceAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerformanceAssessmentController extends Controller
{
    /**
     * List assessments owned by the logged-in medical staff.
     */
    public function index(Request $request): View
    {
        $assessments = PerformanceAssessment::with('assessmentPeriod')
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(10);

        return view('pegawai_medis.assessments.index', compact('assessments'));
    }

    /**
     * Show a single assessment (read-only with details).
     */
    public function show(PerformanceAssessment $assessment): View
    {
        $this->authorizeSelf($assessment);
        $assessment->load(['assessmentPeriod','details.performanceCriteria']);
        return view('pegawai_medis.assessments.show', compact('assessment'));
    }

    /**
     * Ensure the record belongs to the logged-in user.
     * If $editable is true, also block when status is VALIDATED.
     */
    private function authorizeSelf(PerformanceAssessment $assessment): void
    {
        abort_unless($assessment->user_id === Auth::id(), 403);
    }
}
