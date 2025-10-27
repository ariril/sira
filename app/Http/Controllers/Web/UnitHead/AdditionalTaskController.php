<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdditionalTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')->orderByDesc('is_active')->orderByDesc('id')->get();
        }

        if ($unitId && Schema::hasTable('additional_tasks')) {
            $builder = DB::table('additional_tasks as t')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
                ->selectRaw('t.id, t.title, t.status, t.start_date, t.due_date, t.points, t.bonus_amount, ap.name as period_name')
                ->where('t.unit_id', $unitId)
                ->orderByDesc('t.id');
            if ($q !== '') {
                $builder->where(function($w) use ($q) {
                    $w->where('t.title','like',"%$q%")
                      ->orWhere('ap.name','like',"%$q%");
                });
            }
            if (!empty($status)) $builder->where('t.status', $status);
            if (!empty($periodId)) $builder->where('t.assessment_period_id', (int) $periodId);

            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->integer('page', 1),
                [ 'path' => $request->url(), 'query' => $request->query(), ]
            );
        }

        return view('kepala_unit.additional_tasks.index', [
            'items' => $items,
            'periods' => $periods,
            'q' => $q,
            'status' => $status,
            'periodId' => $periodId,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorizeAccess();
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')->orderByDesc('is_active')->orderByDesc('id')->get();
        }
        return view('kepala_unit.additional_tasks.create', [ 'periods' => $periods ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;
        $data = $request->validate([
            'assessment_period_id' => ['nullable','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'start_date'  => ['required','date'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'bonus_amount'=> ['nullable','numeric','min:0'],
            'points'      => ['nullable','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'status'      => ['nullable','in:draft,open,closed,cancelled'],
        ]);

        DB::table('additional_tasks')->insert([
            'unit_id' => $unitId,
            'assessment_period_id' => $data['assessment_period_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'bonus_amount' => $data['bonus_amount'] ?? null,
            'points' => $data['points'] ?? null,
            'max_claims' => $data['max_claims'] ?? 1,
            'status' => $data['status'] ?? 'open',
            'created_by' => $me->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('kepala_unit.additional-tasks.index')->with('status','Tugas dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): RedirectResponse { return redirect()->route('kepala_unit.additional-tasks.edit', $id); }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('additional_tasks')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')->orderByDesc('is_active')->orderByDesc('id')->get();
        }
        return view('kepala_unit.additional_tasks.edit', [ 'item' => $row, 'periods' => $periods ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('additional_tasks')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        $data = $request->validate([
            'assessment_period_id' => ['nullable','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'start_date'  => ['required','date'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'bonus_amount'=> ['nullable','numeric','min:0'],
            'points'      => ['nullable','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'status'      => ['nullable','in:draft,open,closed,cancelled'],
        ]);
        DB::table('additional_tasks')->where('id', $id)->update([
            'assessment_period_id' => $data['assessment_period_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
            'bonus_amount' => $data['bonus_amount'] ?? null,
            'points' => $data['points'] ?? null,
            'max_claims' => $data['max_claims'] ?? 1,
            'status' => $data['status'] ?? $row->status,
            'updated_at' => now(),
        ]);
        return redirect()->route('kepala_unit.additional-tasks.index')->with('status','Tugas diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('additional_tasks')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        DB::table('additional_tasks')->where('id', $id)->delete();
        return back()->with('status','Tugas dihapus.');
    }

    // State transitions
    public function open(string $id): RedirectResponse { return $this->setStatus($id, 'open'); }
    public function close(string $id): RedirectResponse { return $this->setStatus($id, 'closed'); }
    public function cancel(string $id): RedirectResponse { return $this->setStatus($id, 'cancelled'); }

    private function setStatus(string $id, string $status): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('additional_tasks')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        DB::table('additional_tasks')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
        return back()->with('status', 'Status tugas diubah menjadi '.$status.'.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }
}
