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
use App\Services\PeriodPerformanceAssessmentService;
use App\Models\AdditionalTaskClaim;

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
            ->leftJoin('additional_task_claims as c', 'c.id', '=', 'ac.claim_id')
            ->leftJoin('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
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

    public function approve(AdditionalContribution $additionalContribution, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ($additionalContribution->user?->unit_id !== $me->unit_id) abort(403);
        $additionalContribution->update([
            'validation_status' => 'Disetujui',
            'reviewer_id'       => $me->id,
            // score/bonus untuk ad-hoc bisa diisi kemudian; jika tidak ada, biarkan nilai existing
            'bonus_awarded'     => $additionalContribution->bonus_awarded,
            'score'             => $additionalContribution->score,
            'supervisor_comment'=> null,
        ]);
        $additionalContribution->refresh();
        if ($additionalContribution->user) {
            Notification::send($additionalContribution->user, new ContributionApprovedNotification($additionalContribution));
        }

        // Jika kontribusi ini terkait klaim tugas tambahan, sinkronkan status klaim agar UI klaim & daftar tugas ikut berubah.
        if (!empty($additionalContribution->claim_id)) {
            $claim = AdditionalTaskClaim::with('task')->find($additionalContribution->claim_id);
            if ($claim && $claim->task?->unit_id === $me->unit_id && in_array($claim->status, ['submitted', 'validated'])) {
                if ($claim->approve($me, 'Disetujui melalui Kontribusi Tambahan')) {
                    $claim->task?->refreshLifecycleStatus();
                }
            }
        }

        // Recalculate Penilaian Saya for this period & unit+profession.
        $u = $additionalContribution->user;
        if ($u && $additionalContribution->assessment_period_id) {
            $perfSvc->recalculateForGroup((int) $additionalContribution->assessment_period_id, $u->unit_id, $u->profession_id);
        }

        return back()->with('status','Kontribusi disetujui.');
    }

    public function reject(AdditionalContribution $additionalContribution, ReviewAdditionalContributionRequest $request, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
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

        // Jika kontribusi ini terkait klaim tugas tambahan, sinkronkan status klaim.
        if (!empty($additionalContribution->claim_id)) {
            $claim = AdditionalTaskClaim::with('task')->find($additionalContribution->claim_id);
            if ($claim && $claim->task?->unit_id === $me->unit_id && in_array($claim->status, ['submitted', 'validated'])) {
                if ($claim->reject($data['comment'] ?? 'Ditolak melalui Kontribusi Tambahan', $me)) {
                    $claim->task?->refreshLifecycleStatus();
                }
            }
        }

        // Recalculate Penilaian Saya for this period & unit+profession.
        $u = $additionalContribution->user;
        if ($u && $additionalContribution->assessment_period_id) {
            $perfSvc->recalculateForGroup((int) $additionalContribution->assessment_period_id, $u->unit_id, $u->profession_id);
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
