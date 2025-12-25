<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAdditionalTaskClaimRequest;
use App\Models\AdditionalTaskClaim;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification as Notify;
use App\Notifications\ClaimApprovedNotification;
use App\Notifications\ClaimRejectedNotification;
use App\Support\AssessmentPeriodGuard;

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
            ->selectRaw("c.id, c.status, c.claimed_at, c.result_file_path, c.result_note, t.due_date, t.points, t.bonus_amount, t.policy_doc_path, t.title as task_title, ap.name as period_name, u.name as user_name")
            ->where('t.unit_id', $me->unit_id)
            ->whereIn('c.status', ['submitted','validated'])
            ->orderByDesc('c.id')
            ->paginate(30);
        return view('kepala_unit.additional_task_claims.review_index',[ 'claims' => $claims ]);
    }

    public function update(ReviewAdditionalTaskClaimRequest $request, AdditionalTaskClaim $claim): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($claim->task?->unit_id !== $me->unit_id) abort(403);

        $claim->loadMissing('task.period');
        AssessmentPeriodGuard::requireActive($claim->task?->period, 'Review Klaim Tugas Tambahan');

        $action = $request->validated()['action'];
        $comment = $request->validated()['comment'] ?? null;

        $ok = false;
        switch ($action) {
            case 'validate':
                // Disederhanakan: validasi tidak lagi jadi langkah terpisah.
                // Untuk kompatibilitas (mis. request lama/test), treat 'validate' sebagai approve.
                $ok = $claim->approve($me, $comment);
                if ($ok && $claim->user) { Notify::send($claim->user, new ClaimApprovedNotification($claim)); }
                break;
            case 'approve':
                $ok = $claim->approve($me, $comment);
                if ($ok && $claim->user) { Notify::send($claim->user, new ClaimApprovedNotification($claim)); }
                break;
            case 'reject':
                $ok = $claim->reject($comment, $me);
                if ($ok && $claim->user) { Notify::send($claim->user, new ClaimRejectedNotification($claim, $comment)); }
                break;
        }

        if ($ok && $claim->task) {
            $claim->task->refreshLifecycleStatus();
        }

        return back()->with('status', $ok ? 'Klaim diperbarui.' : 'Transisi tidak valid.');
    }
}
