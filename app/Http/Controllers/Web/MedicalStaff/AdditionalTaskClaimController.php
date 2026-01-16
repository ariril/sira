<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Http\Requests\SubmitAdditionalTaskClaimResultRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ClaimSubmittedNotification;
use App\Services\AdditionalTasks\AdditionalTaskService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;

class AdditionalTaskClaimController extends Controller
{
    // Klaim tugas (muncul di Progress Klaim Saya)
    public function claim(Request $request, AdditionalTask $task, AdditionalTaskService $svc): RedirectResponse
    {
        $me = Auth::user();
        abort_unless((bool) $me, 403);
        abort_unless((int) $task->unit_id === (int) $me->unit_id, 403);

        try {
            $svc->claimTask($task, $me);
            $task->refreshLifecycleStatus();
            return back()->with('status', 'Tugas berhasil diklaim.');
        } catch (\Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    }

    // Submit hasil tugas (langsung membuat claim: submitted/auto-rejected)
    public function submit(SubmitAdditionalTaskClaimResultRequest $request, AdditionalTask $task, AdditionalTaskService $svc): RedirectResponse
    {
        $me = Auth::user();
        abort_unless((bool) $me, 403);
        abort_unless((int) $task->unit_id === (int) $me->unit_id, 403);

        try {
            $claim = $svc->submitClaimResult($task, $me, [
                'note' => $request->input('note'),
                'file' => $request->file('result_file'),
            ]);

            // Notify unit head(s)
            $heads = \App\Models\User::query()
                ->where('unit_id', $task->unit_id)
                ->whereHas('roles', fn($q) => $q->where('slug', 'kepala_unit'))
                ->get();
            if ($heads->isNotEmpty() && $claim->status === 'submitted') {
                Notification::send($heads, new ClaimSubmittedNotification($claim));
            }

            $task->refreshLifecycleStatus();

            $msg = match ($claim->status) {
                'submitted' => 'Hasil tugas dikirim, menunggu review.',
                'rejected' => 'Hasil ditolak otomatis karena melewati batas waktu.',
                'approved' => 'Klaim sudah disetujui.',
                default => 'Klaim diproses.',
            };
            return back()->with('status', $msg);
        } catch (\Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    }
}
