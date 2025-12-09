<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\AdditionalContribution;
use App\Models\AdditionalTask;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\ReviewAdditionalContributionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Notifications\ContributionApprovedNotification;
use App\Notifications\ContributionRejectedNotification;

class AdditionalContributionReviewController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $status = $request->query('status');
        $builder = DB::table('additional_contributions as ac')
            ->join('users as u','u.id','=','ac.user_id')
            ->leftJoin('additional_tasks as t','t.id','=','ac.task_id')
            ->selectRaw("ac.id, ac.title, ac.validation_status, ac.submission_date, ac.evidence_file, u.name as user_name, t.title as task_title")
            ->where('u.unit_id', $me->unit_id)
            ->orderByDesc('ac.id');
        if ($status) $builder->where('ac.validation_status', $status);
        $items = $builder->paginate(20)->withQueryString();
        return view('kepala_unit.additional_contributions.index', [ 'items' => $items, 'status' => $status ]);
    }

    public function show(AdditionalContribution $additionalContribution): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($additionalContribution->user?->unit_id !== $me->unit_id) abort(403);
        return view('kepala_unit.additional_contributions.show', [ 'item' => $additionalContribution ]);
    }

    public function approve(AdditionalContribution $additionalContribution): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($additionalContribution->user?->unit_id !== $me->unit_id) abort(403);
        $bonus = null; $score = null;
        if ($additionalContribution->task_id && $additionalContribution->task) {
            $bonus = $additionalContribution->task->bonus_amount;
            $score = $additionalContribution->task->points;
        }
        $additionalContribution->update([
            'validation_status' => 'Disetujui',
            'reviewer_id'       => $me->id,
            'bonus_awarded'     => $bonus,
            'score'             => $score,
            'supervisor_comment'=> null,
        ]);
        $additionalContribution->refresh();
        if ($additionalContribution->user) {
            Notification::send($additionalContribution->user, new ContributionApprovedNotification($additionalContribution));
        }
        return back()->with('status','Kontribusi disetujui.');
    }

    public function reject(AdditionalContribution $additionalContribution, ReviewAdditionalContributionRequest $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($additionalContribution->user?->unit_id !== $me->unit_id) abort(403);
        $data = $request->validated();
        $additionalContribution->update([
            'validation_status' => 'Ditolak',
            'reviewer_id'       => $me->id,
            'supervisor_comment'=> $data['comment'] ?? null,
            'bonus_awarded'     => null,
        ]);
        $additionalContribution->refresh();
        if ($additionalContribution->user) {
            Notification::send($additionalContribution->user, new ContributionRejectedNotification($additionalContribution));
        }
        return back()->with('status','Kontribusi ditolak.');
    }
    public function download(AdditionalContribution $additionalContribution): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($additionalContribution->user?->unit_id !== $me->unit_id) abort(403);
        if (!$additionalContribution->evidence_file) abort(404);
        $path = storage_path('app/'.$additionalContribution->evidence_file);
        if (!file_exists($path)) abort(404);
        $name = $additionalContribution->attachment_original_name ?: 'lampiran-kontribusi-'.$additionalContribution->id;
        return response()->download($path, $name);
    }
}
