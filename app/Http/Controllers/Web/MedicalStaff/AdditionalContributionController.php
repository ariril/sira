<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\StoreAdditionalContributionRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Services\AdditionalTaskStatusService;
use Illuminate\Support\Carbon;

class AdditionalContributionController extends Controller
{
    /**
     * Show available and claimed/completed additional tasks for the user.
     */
    public function index(Request $request): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);

        $unitId = $me->unit_id;
        $availableTasks = collect();
        $currentClaims = collect();
        $historyClaims = collect();
        $latestRejected = null;

        if (Schema::hasTable('additional_tasks') && Schema::hasTable('additional_task_claims')) {
            AdditionalTaskStatusService::syncForUnit($unitId);

            $availableTasks = AdditionalTask::query()
                ->with(['period:id,name'])
                ->withCount([
                    'claims as active_claims' => function ($query) {
                        $query->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    },
                ])
                ->where('unit_id', $unitId)
                ->where('status', 'open')
                ->orderBy('due_date')
                ->orderBy('due_time')
                ->get();

            $taskIds = $availableTasks->pluck('id');
            $myClaimMap = AdditionalTaskClaim::query()
                ->select('id', 'additional_task_id', 'status')
                ->where('user_id', $me->id)
                ->whereIn('additional_task_id', $taskIds)
                ->latest('claimed_at')
                ->get()
                ->keyBy('additional_task_id');

            $availableTasks = $availableTasks->map(function (AdditionalTask $task) use ($myClaimMap) {
                $myClaim = $myClaimMap->get($task->id);
                return (object) [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'period_name' => $task->period?->name,
                    'due_date' => $task->due_date,
                    'due_time' => $task->due_time,
                    'bonus_amount' => $task->bonus_amount,
                    'points' => $task->points,
                    'claims_used' => (int) $task->active_claims,
                    'max_claims' => $task->max_claims,
                    'available' => empty($task->max_claims) || $task->active_claims < (int) $task->max_claims,
                    'supporting_file_url' => $task->policy_doc_path
                        ? asset('storage/' . ltrim($task->policy_doc_path, '/'))
                        : null,
                    'my_claim_id' => $myClaim->id ?? null,
                    'my_claim_status' => $myClaim->status ?? null,
                ];
            })->values();

            $claimBase = AdditionalTaskClaim::query()
                ->with(['task.period'])
                ->where('user_id', $me->id)
                ->orderByDesc('claimed_at');

            $latestRejected = (clone $claimBase)
                ->where('status', 'rejected')
                ->latest('updated_at')
                ->first();

            $currentClaims = (clone $claimBase)
                ->whereIn('status', ['active', 'submitted', 'validated'])
                ->get();

            $historyClaims = (clone $claimBase)
                ->whereIn('status', ['approved', 'completed', 'rejected', 'cancelled'])
                ->limit(30)
                ->get();
        }

        return view('pegawai_medis.additional_contributions.index', [
            'availableTasks' => $availableTasks,
            'currentClaims' => $currentClaims,
            'historyClaims' => $historyClaims,
            'latestRejected' => $latestRejected,
        ]);
    }

    /** Form create contribution evidence */
    public function create(): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);
        // daftar klaim aktif agar bisa memilih tugas terkait
        $claims = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t','t.id','=','c.additional_task_id')
            ->selectRaw('c.id as claim_id, t.id as task_id, t.title')
            ->where('c.user_id', $me->id)
            ->whereIn('c.status',['active','submitted','validated','approved'])
            ->orderByDesc('c.id')
            ->get();
        return view('pegawai_medis.additional_contributions.create', [ 'claims' => $claims ]);
    }

    /** Store contribution with file upload */
    public function store(StoreAdditionalContributionRequest $request): RedirectResponse
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);
        $data = $request->validated();

        $taskId = null; $periodId = null; $bonus = null; $score = null;
        if (!empty($data['claim_id'])) {
            $claim = DB::table('additional_task_claims as c')
                ->join('additional_tasks as t','t.id','=','c.additional_task_id')
                ->selectRaw('c.id, t.id as task_id, t.assessment_period_id, t.bonus_amount, t.points')
                ->where('c.id', (int)$data['claim_id'])
                ->where('c.user_id', $me->id)
                ->first();
            if ($claim) {
                $taskId   = $claim->task_id;
                $periodId = $claim->assessment_period_id;
                $bonus    = $claim->bonus_amount;
                $score    = $claim->points;
            }
        }

        $storedPath = null; $mime = null; $orig = null; $size = null;
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $storedPath = $f->store('additional_contributions');
            $mime = $f->getMimeType();
            $orig = $f->getClientOriginalName();
            $size = $f->getSize();
        }

        DB::table('additional_contributions')->insert([
            'user_id'                 => $me->id,
            'task_id'                 => $taskId,
            'title'                   => $data['title'],
            'description'             => $data['description'] ?? null,
            'submission_date'         => now()->toDateString(),
            'submitted_at'            => now(),
            'evidence_file'           => $storedPath,
            'attachment_original_name'=> $orig,
            'attachment_mime'         => $mime,
            'attachment_size'         => $size,
            'validation_status'       => 'Menunggu Persetujuan',
            'assessment_period_id'    => $periodId,
            'score'                   => $score,
            'bonus_awarded'           => null, // diisi saat di-approve
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        return redirect()->route('pegawai_medis.additional_contributions.index')->with('status','Kontribusi dikirim & menunggu persetujuan.');
    }
}
