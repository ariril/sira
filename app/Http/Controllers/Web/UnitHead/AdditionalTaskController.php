<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Services\AdditionalTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Models\AssessmentPeriod;

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
            'status'    => ['nullable','string','in:draft,open,closed,cancelled'],
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
            $builder = DB::table('additional_tasks as t')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
                ->selectRaw('t.id, t.title, t.status, t.start_date, t.start_time, t.due_date, t.due_time, t.points, t.bonus_amount, ap.name as period_name')
                ->where('t.unit_id', $unitId)
                ->orderByDesc('t.id');

            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('t.title', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            if (!empty($status)) {
                $builder->where('t.status', $status);
            }
            if (!empty($periodId)) {
                $builder->where('t.assessment_period_id', (int) $periodId);
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
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')
                ->get();
        }

        return view('kepala_unit.additional_tasks.create', ['periods' => $periods]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;

        $data = $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'start_date'  => ['required','date'],
            'start_time'  => ['nullable','date_format:H:i'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'due_time'    => ['nullable','date_format:H:i'],
            'bonus_amount'=> ['nullable','numeric','min:0'],
            'points'      => ['nullable','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'supporting_file' => ['nullable','file','max:10240','mimes:doc,docx,xls,xlsx,ppt,pptx'],
        ]);

        $hasBonus = $request->filled('bonus_amount');
        $hasPoints = $request->filled('points');
        if (!$hasBonus && !$hasPoints) {
            return back()->withErrors([
                'bonus_amount' => 'Isi Bonus atau Poin minimal salah satu.',
                'points' => 'Isi Bonus atau Poin minimal salah satu.',
            ])->withInput();
        }
        if ($hasBonus && $hasPoints) {
            return back()->withErrors([
                'bonus_amount' => 'Pilih salah satu antara Bonus atau Poin.',
                'points' => 'Pilih salah satu antara Bonus atau Poin.',
            ])->withInput();
        }

        $startTime = $data['start_time'] ?? '00:00';
        $dueTime = $data['due_time'] ?? '23:59';

        $startAt = Carbon::createFromFormat('Y-m-d H:i', $data['start_date'].' '.$startTime, 'Asia/Jakarta');
        $dueAt   = Carbon::createFromFormat('Y-m-d H:i', $data['due_date'].' '.$dueTime, 'Asia/Jakarta');

        if ($dueAt->lt($startAt)) {
            return back()->withErrors([
                'due_time' => 'Jatuh tempo harus setelah atau sama dengan waktu mulai.',
            ])->withInput();
        }

        $filePath = null;
        if ($request->hasFile('supporting_file')) {
            $filePath = $request->file('supporting_file')->store('additional_tasks/supporting', 'public');
        }

        $task = AdditionalTask::create([
            'unit_id' => $unitId,
            'assessment_period_id' => $data['assessment_period_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'due_date' => $dueAt->toDateString(),
            'due_time' => $dueAt->format('H:i:s'),
            'bonus_amount' => $data['bonus_amount'] ?? null,
            'points' => $data['points'] ?? null,
            'max_claims' => $data['max_claims'] ?? 1,
            'status' => 'open',
            'policy_doc_path' => $filePath,
            'created_by' => $me->id,
        ]);

        $task->refreshLifecycleStatus();
        $task->refresh();

        if ($task->status === 'open' && class_exists(\App\Notifications\AdditionalTaskAvailableNotification::class)) {
            $targets = DB::table('users as u')
                ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->select('u.id')
                ->where('u.unit_id', $unitId)
                ->where('r.slug', 'pegawai_medis')
                ->get();

            foreach ($targets as $target) {
                $user = \App\Models\User::find($target->id);
                if ($user) {
                    $user->notify(new \App\Notifications\AdditionalTaskAvailableNotification($task));
                }
            }
        }

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

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $task = AdditionalTask::find($id);
        if (!$task) abort(404);
        if ((int) $task->unit_id !== (int) $me->unit_id) abort(403);

        $data = $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'start_date'  => ['required','date'],
            'start_time'  => ['nullable','date_format:H:i'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'due_time'    => ['nullable','date_format:H:i'],
            'bonus_amount'=> ['nullable','numeric','min:0'],
            'points'      => ['nullable','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'supporting_file' => ['nullable','file','max:10240','mimes:doc,docx,xls,xlsx,ppt,pptx'],
        ]);

        $hasBonus = $request->filled('bonus_amount');
        $hasPoints = $request->filled('points');
        if (!$hasBonus && !$hasPoints) {
            return back()->withErrors([
                'bonus_amount' => 'Isi Bonus atau Poin minimal salah satu.',
                'points' => 'Isi Bonus atau Poin minimal salah satu.',
            ])->withInput();
        }
        if ($hasBonus && $hasPoints) {
            return back()->withErrors([
                'bonus_amount' => 'Pilih salah satu antara Bonus atau Poin.',
                'points' => 'Pilih salah satu antara Bonus atau Poin.',
            ])->withInput();
        }

        $startTime = $data['start_time'] ?? ($task->start_time ? substr($task->start_time, 0, 5) : '00:00');
        $dueTime = $data['due_time'] ?? ($task->due_time ? substr($task->due_time, 0, 5) : '23:59');

        $startAt = Carbon::createFromFormat('Y-m-d H:i', $data['start_date'].' '.$startTime, 'Asia/Jakarta');
        $dueAt   = Carbon::createFromFormat('Y-m-d H:i', $data['due_date'].' '.$dueTime, 'Asia/Jakarta');

        if ($dueAt->lt($startAt)) {
            return back()->withErrors([
                'due_time' => 'Jatuh tempo harus setelah atau sama dengan waktu mulai.',
            ])->withInput();
        }

        $filePath = $task->policy_doc_path;
        if ($request->hasFile('supporting_file')) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $request->file('supporting_file')->store('additional_tasks/supporting', 'public');
        }

        $task->update([
            'assessment_period_id' => $data['assessment_period_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'due_date' => $dueAt->toDateString(),
            'due_time' => $dueAt->format('H:i:s'),
            'bonus_amount' => $data['bonus_amount'] ?? null,
            'points' => $data['points'] ?? null,
            'max_claims' => $data['max_claims'] ?? 1,
            'policy_doc_path' => $filePath,
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

        if ($task->policy_doc_path) {
            Storage::disk('public')->delete($task->policy_doc_path);
        }

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

    public function cancel(string $id): RedirectResponse
    {
        return $this->setStatus($id, 'cancelled');
    }

    private function setStatus(string $id, string $status): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $task = AdditionalTask::find($id);
        if (!$task) abort(404);
        if ((int) $task->unit_id !== (int) $me->unit_id) abort(403);

        $current = $task->status;
        $now = Carbon::now('Asia/Jakarta');

        if ($status === 'open') {
            if ($current === 'open') {
                return back()->with('status', 'Tugas sudah dalam status open.');
            }

            if ($task->due_date) {
                $dueEnd = Carbon::parse($task->due_date, 'Asia/Jakarta')->endOfDay();
                if ($dueEnd->lessThan($now)) {
                    return back()->with('status', 'Perbarui tanggal selesai pada menu Edit sebelum membuka kembali tugas ini.');
                }
            }

            if (!in_array($current, ['draft', 'cancelled', 'closed'])) {
                return back()->with('status', 'Status saat ini tidak dapat dibuka secara manual.');
            }
        }

        if ($status === 'closed' && $current !== 'open') {
            return back()->with('status', 'Hanya tugas berstatus open yang bisa ditutup.');
        }

        if ($status === 'cancelled' && !in_array($current, ['open','draft'])) {
            return back()->with('status', 'Status saat ini tidak dapat dibatalkan.');
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
