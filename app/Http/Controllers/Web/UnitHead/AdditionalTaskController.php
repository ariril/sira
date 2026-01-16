<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdditionalTaskRequest;
use App\Http\Requests\UpdateAdditionalTaskRequest;
use App\Models\AdditionalTask;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Models\AssessmentPeriod;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Carbon;

class AdditionalTaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;

        $perPageOptions = [10, 20, 30, 50];
        $data = $request->validate([
            'q'         => ['nullable','string','max:100'],
            'status'    => ['nullable','string','in:open,closed'],
            'period_id' => ['nullable','integer'],
            'per_page'  => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);
        $q        = (string)($data['q'] ?? '');
        $status   = $data['status'] ?? '';
        $periodId = $data['period_id'] ?? null;
        $perPage  = (int) ($data['per_page'] ?? 20);

        $periods = collect();
        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')
                ->get();
            $activePeriod = $periods->firstWhere('status', 'active');
        }

        if ($unitId) {
            AdditionalTaskStatusService::syncForUnit($unitId);
        }

        if ($unitId && Schema::hasTable('additional_tasks')) {
            $builder = AdditionalTask::query()
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'additional_tasks.assessment_period_id')
                ->select([
                    'additional_tasks.id',
                    'additional_tasks.title',
                    'additional_tasks.status',
                    'additional_tasks.due_date',
                    'additional_tasks.due_time',
                    'additional_tasks.points',
                    'additional_tasks.max_claims',
                ])
                ->addSelect('ap.name as period_name')
                ->withCount([
                    'claims as total_claims' => function ($q) {
                        // Count all claims (any status) to prevent unsafe actions like delete.
                    },
                    'claims as active_claims' => function ($q) {
                        $q->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    },
                    'claims as review_waiting' => function ($q) {
                        $q->whereIn('status', AdditionalTaskStatusService::REVIEW_WAITING_STATUSES);
                    },
                    'claims as finished_claims' => function ($q) {
                        $q->whereIn('status', ['approved', 'rejected']);
                    },
                ])
                ->where('additional_tasks.unit_id', $unitId)
                ->orderByDesc('additional_tasks.id');

            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('additional_tasks.title', 'like', "%$q%")
                        ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            if (!empty($status)) {
                $builder->where('additional_tasks.status', $status);
            }
            if (!empty($periodId)) {
                $builder->where('additional_tasks.assessment_period_id', (int) $periodId);
            }

            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->integer('page', 1),
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('kepala_unit.additional_tasks.index', compact(
            'items', 'periods', 'q', 'status', 'periodId', 'perPage', 'perPageOptions', 'activePeriod'
        ));
    }

    public function create(): View
    {
        $this->authorizeAccess();
        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            $activePeriod = AssessmentPeriod::query()->active()->orderByDesc('id')->first();
        }

        return view('kepala_unit.additional_tasks.create', ['activePeriod' => $activePeriod]);
    }

    public function store(StoreAdditionalTaskRequest $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;

        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            // Pastikan periode aktif up-to-date
            AssessmentPeriod::syncByNow();
            $activePeriod = AssessmentPeriod::query()->active()->orderByDesc('id')->first();
        }
        if (!$activePeriod) {
            return back()->withErrors([
                'title' => 'Tidak ada periode yang aktif saat ini. Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.',
            ])->withInput();
        }

        AssessmentPeriodGuard::requireActive($activePeriod, 'Buat Tugas Tambahan');

        $tz = config('app.timezone');
        $data = $request->validated();

        $dueTime = $data['due_time'] ?? '23:59';
        $dueAt = Carbon::createFromFormat('Y-m-d H:i', $data['due_date'].' '.$dueTime, $tz);
        if ($dueAt->isPast()) {
            return back()->withErrors([
                'due_date' => 'Jatuh tempo tidak boleh sudah lewat.',
            ])->withInput();
        }

        $task = AdditionalTask::create([
            'unit_id' => $unitId,
            'assessment_period_id' => $activePeriod->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $dueAt->toDateString(),
            'due_time' => $dueAt->format('H:i:s'),
            'points' => $data['points'],
            'max_claims' => $data['max_claims'] ?? 1,
            'status' => 'open',
            'created_by' => $me->id,
        ]);

        $task->refreshLifecycleStatus();
        $task->refresh();

        return redirect()->route('kepala_unit.additional-tasks.index')->with('status', 'Tugas dibuat.');
    }

    public function show(string $id): RedirectResponse
    {
        return redirect()->route('kepala_unit.additional-tasks.edit', $id);
    }

    public function edit(string $id): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('additional_tasks')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int) $row->unit_id !== (int) $me->unit_id) abort(403);

        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')
                ->get();
        }

        return view('kepala_unit.additional_tasks.edit', ['item' => $row, 'periods' => $periods]);
    }

    public function update(UpdateAdditionalTaskRequest $request, string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $task = AdditionalTask::find($id);
        if (!$task) abort(404);
        if ((int) $task->unit_id !== (int) $me->unit_id) abort(403);

        $task->loadMissing('period');
        AssessmentPeriodGuard::requireActive($task->period, 'Ubah Tugas Tambahan');

        $data = $request->validated();

        $targetPeriod = AssessmentPeriod::query()->find((int) $data['assessment_period_id']);
        AssessmentPeriodGuard::requireActive($targetPeriod, 'Ubah Tugas Tambahan');

        $tz = config('app.timezone');
        $dueTime = $data['due_time'] ?? ($task->due_time ? substr((string) $task->due_time, 0, 5) : '23:59');
        $dueAt = Carbon::createFromFormat('Y-m-d H:i', $data['due_date'].' '.$dueTime, $tz);

        $task->update([
            'assessment_period_id' => $data['assessment_period_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $dueAt->toDateString(),
            'due_time' => $dueAt->format('H:i:s'),
            'points' => $data['points'],
            'max_claims' => $data['max_claims'] ?? 1,
        ]);
        $task->refreshLifecycleStatus();

        return redirect()->route('kepala_unit.additional-tasks.index')->with('status', 'Tugas diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $task = AdditionalTask::find($id);
        if (!$task) abort(404);
        if ((int) $task->unit_id !== (int) $me->unit_id) abort(403);

        if ($task->claims()->exists()) {
            return back()->with('status', 'Tugas tidak dapat dihapus karena sudah memiliki klaim.');
        }

        $task->loadMissing('period');
        AssessmentPeriodGuard::requireActive($task->period, 'Hapus Tugas Tambahan');

        $task->delete();

        return back()->with('status', 'Tugas dihapus.');
    }

    public function open(string $id): RedirectResponse
    {
        return $this->setStatus($id, 'open');
    }

    public function close(string $id): RedirectResponse
    {
        return $this->setStatus($id, 'closed');
    }

    private function setStatus(string $id, string $status): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $task = AdditionalTask::find($id);
        if (!$task) abort(404);
        if ((int) $task->unit_id !== (int) $me->unit_id) abort(403);

        $task->loadMissing('period');
        AssessmentPeriodGuard::requireActive($task->period, 'Ubah Status Tugas Tambahan');

        $current = $task->status;
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        if ($status === 'open') {
            if ($current === 'open') {
                return back()->with('status', 'Tugas sudah dalam status open.');
            }

            if ($task->due_date) {
                $dueEnd = Carbon::parse($task->due_date, $tz)->endOfDay();
                if ($dueEnd->lessThan($now)) {
                    return back()->with('status', 'Perbarui tanggal selesai pada menu Edit sebelum membuka kembali tugas ini.');
                }
            }

            if ($current !== 'closed') {
                return back()->with('status', 'Hanya tugas berstatus closed yang bisa dibuka kembali.');
            }
        }

        if ($status === 'closed' && $current !== 'open') {
            return back()->with('status', 'Hanya tugas berstatus open yang bisa ditutup.');
        }

        $task->update(['status' => $status]);
        if ($status === 'open') {
            $task->refreshLifecycleStatus();
        }

        return back()->with('status', 'Status tugas diubah menjadi ' . $status . '.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') {
            abort(403);
        }
    }
}
