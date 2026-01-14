<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAdditionalTaskClaimRequest;
use App\Models\AdditionalTaskClaim;
use App\Services\AdditionalTasks\AdditionalTaskService;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Notifications\ClaimApprovedNotification;
use App\Notifications\ClaimRejectedNotification;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Facades\Notification;

class AdditionalTaskClaimReviewController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }

    public function index(): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $claims = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t','t.id','=','c.additional_task_id')
            ->leftJoin('assessment_periods as ap','ap.id','=','t.assessment_period_id')
            ->join('users as u','u.id','=','c.user_id')
            ->selectRaw("c.id, c.status, c.submitted_at, c.reviewed_at, c.review_comment, c.awarded_points, c.result_file_path, c.result_note, t.due_date, t.due_time, t.points, t.title as task_title, ap.name as period_name, u.name as user_name")
            ->where('t.unit_id', $me->unit_id)
            ->whereIn('c.status', AdditionalTaskStatusService::REVIEW_WAITING_STATUSES)
            ->orderByDesc('c.id')
            ->paginate(30);
        return view('kepala_unit.additional_task_claims.review_index',[ 'claims' => $claims ]);
    }

    public function update(ReviewAdditionalTaskClaimRequest $request, AdditionalTaskClaim $claim, AdditionalTaskService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($claim->task?->unit_id !== $me->unit_id) abort(403);

        $claim->loadMissing('task.period');
        AssessmentPeriodGuard::requireActiveOrRevision($claim->task?->period, 'Review Klaim Tugas Tambahan');

        $action = (string) ($request->validated()['action'] ?? '');
        $comment = $request->validated()['comment'] ?? null;

        $decision = match ($action) {
            'validate', 'approve' => 'approved',
            'reject' => 'rejected',
            default => '',
        };

        try {
            $svc->reviewClaim($claim, $me, [
                'decision' => $decision,
                'comment' => $comment,
            ]);

            if ($claim->user) {
                if ($claim->status === 'approved') {
                    Notification::send($claim->user, new ClaimApprovedNotification($claim));
                } elseif ($claim->status === 'rejected') {
                    Notification::send($claim->user, new ClaimRejectedNotification($claim, $comment));
                }
            }

            if ($claim->task) {
                $claim->task->refreshLifecycleStatus();
            }

            return back()->with('status', 'Klaim diperbarui.');
        } catch (\Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    }
}
