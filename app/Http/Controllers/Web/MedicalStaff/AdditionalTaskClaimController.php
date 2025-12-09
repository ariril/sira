<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Http\Requests\SubmitAdditionalTaskClaimResultRequest;
use Illuminate\Support\Facades\Notification as Notify;
use App\Notifications\ClaimSubmittedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdditionalTaskAvailableAgainNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\AdditionalTaskStatusService;
use Illuminate\Support\Carbon;

class AdditionalTaskClaimController extends Controller
{
    /**
     * Claim an available task for the logged-in user.
     */
    public function claim(AdditionalTask $task): RedirectResponse
    {
        $me = Auth::user();
        abort_unless((bool)$me, 403);
        AdditionalTaskStatusService::sync($task);
        $task->refresh();

        $dueEnd = $task->due_date ? Carbon::parse($task->due_date, 'Asia/Jakarta')->endOfDay() : null;
        if ((int)$task->unit_id !== (int)$me->unit_id || $task->status !== 'open' || ($dueEnd && Carbon::now('Asia/Jakarta')->greaterThan($dueEnd))) {
            abort(403);
        }

        $created = false; $reason = '';
        \DB::transaction(function () use (&$created, &$reason, $task, $me) {
            // Lock task row (if using InnoDB) to avoid race on max_claims
            $lockedTask = \DB::table('additional_tasks')->where('id', $task->id)->lockForUpdate()->first();
            if (!$lockedTask || $lockedTask->status !== 'open') { $reason = 'Tugas tidak tersedia.'; return; }

            $already = AdditionalTaskClaim::where('additional_task_id', $task->id)
                ->where('user_id', $me->id)
                ->where('status', 'active')
                ->exists();
            if ($already) { $reason = 'Anda sudah mengklaim tugas ini.'; return; }

            $used = AdditionalTaskClaim::where('additional_task_id', $task->id)
                ->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES)
                ->count();
            if (!empty($task->max_claims) && $used >= (int)$task->max_claims) { $reason = 'Kuota klaim habis.'; return; }

            AdditionalTaskClaim::create([
                'additional_task_id' => $task->id,
                'user_id'            => $me->id,
                'status'             => 'active',
                'claimed_at'         => now(),
                'cancel_deadline_at' => now()->addDay(),
                'penalty_type'       => 'none',
                'penalty_value'      => 0,
            ]);
            $created = true; $reason = 'Tugas berhasil diklaim.';
        });
        $task->refreshLifecycleStatus();
        return back()->with('status', $reason);
    }

    /** Cancel my active claim. */
    public function cancel(AdditionalTaskClaim $claim): RedirectResponse
    {
        $me = Auth::user();
        abort_unless($me && $claim->user_id === $me->id, 403);
        $claim->cancel('Dibatalkan oleh pegawai');

        // Jika slot kembali tersedia (kuota belum penuh) kirim notifikasi ke pegawai medis lain di unit
        $task = $claim->task;
        if ($task && $task->status === 'open') {
            $activeCount = AdditionalTaskClaim::where('additional_task_id', $task->id)
                ->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES)
                ->count();
            if ($activeCount < (int)$task->max_claims) {
                $targets = User::query()
                    ->where('unit_id', $task->unit_id)
                    ->whereHas('roles', fn($q) => $q->where('slug','pegawai_medis'))
                    ->whereNotExists(function($q) use ($task) {
                        $q->selectRaw(1)
                          ->from('additional_task_claims as c2')
                          ->whereColumn('c2.user_id','users.id')
                          ->where('c2.additional_task_id', $task->id)
                          ->whereIn('c2.status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    })->get();
                if ($targets->isNotEmpty()) {
                    Notification::send($targets, new AdditionalTaskAvailableAgainNotification($task));
                }
            }
            $task->refreshLifecycleStatus();
        }
        return back()->with('status', 'Klaim dibatalkan.');
    }

    /** Complete my active claim. */
    public function complete(AdditionalTaskClaim $claim): RedirectResponse
    {
        $me = Auth::user();
        abort_unless($me && $claim->user_id === $me->id, 403);
        if ($claim->status !== 'active') return back();
        $claim->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        if ($claim->task) {
            $claim->task->refreshLifecycleStatus();
        }
        return back()->with('status', 'Tugas ditandai selesai.');
    }

    // Submit hasil untuk review (active -> submitted)
    public function submit(SubmitAdditionalTaskClaimResultRequest $request, AdditionalTaskClaim $claim): RedirectResponse
    {
        $me = Auth::user();
        abort_unless($me && $claim->user_id === $me->id, 403);
        if ($claim->status !== 'active') {
            return back()->with('status', 'Tidak dapat submit pada status saat ini.');
        }

        $filePath = $request->file('result_file')->store('additional_tasks/results', 'public');
        if ($claim->result_file_path) {
            Storage::disk('public')->delete($claim->result_file_path);
        }

        $claim->update([
            'result_note' => $request->input('note'),
            'result_file_path' => $filePath,
        ]);

        if ($claim->submitResult()) {
            // Notify unit head(s) - simple broadcast to all kepala_unit users in same unit
            $task = $claim->task;
            if ($task) {
                $heads = \App\Models\User::query()
                    ->where('unit_id', $task->unit_id)
                    ->whereHas('roles', fn($q) => $q->where('slug','kepala_unit'))
                    ->get();
                if ($heads->isNotEmpty()) {
                    Notify::send($heads, new ClaimSubmittedNotification($claim));
                }
                $task->refreshLifecycleStatus();
            }
            return back()->with('status', 'Hasil tugas dikirim, menunggu review.');
        }
        return back()->with('status', 'Tidak dapat submit pada status saat ini.');
    }
}
