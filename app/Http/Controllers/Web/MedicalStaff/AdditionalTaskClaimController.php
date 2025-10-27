<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdditionalTaskClaimController extends Controller
{
    /**
     * Claim an available task for the logged-in user.
     */
    public function claim(AdditionalTask $task): RedirectResponse
    {
    $me = Auth::user();
    abort_unless((bool)$me, 403);
        // task must be in my unit and open
        if ((int)$task->unit_id !== (int)$me->unit_id || $task->status !== 'open') {
            abort(403);
        }

        // prevent duplicate active claim by me
        $already = AdditionalTaskClaim::where('additional_task_id', $task->id)
            ->where('user_id', $me->id)
            ->where('status', 'active')
            ->exists();
        if ($already) {
            return back()->with('status', 'Anda sudah mengklaim tugas ini.');
        }

        // check quota
        $used = AdditionalTaskClaim::where('additional_task_id', $task->id)
            ->whereIn('status', ['active','completed'])
            ->count();
        if (!empty($task->max_claims) && $used >= (int)$task->max_claims) {
            return back()->with('status', 'Kuota klaim untuk tugas ini sudah habis.');
        }

        AdditionalTaskClaim::create([
            'additional_task_id' => $task->id,
            'user_id'            => $me->id,
            'status'             => 'active',
            'claimed_at'         => now(),
            'cancel_deadline_at' => now()->addDay(),
            'penalty_type'       => 'none',
            'penalty_value'      => 0,
        ]);

        return back()->with('status', 'Tugas berhasil diklaim.');
    }

    /** Cancel my active claim. */
    public function cancel(AdditionalTaskClaim $claim): RedirectResponse
    {
        $me = Auth::user();
        abort_unless($me && $claim->user_id === $me->id, 403);
        $claim->cancel('Dibatalkan oleh pegawai');
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
        return back()->with('status', 'Tugas ditandai selesai.');
    }
}
