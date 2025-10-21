<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\AssessmentPeriod;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitRemunerationAllocationController extends Controller
{
    protected function perPageOptions(): array { return [5, 10, 12, 20, 30, 50]; }

    /** List allocations with filters */
    public function index(Request $request): View
    {
        $perPageOptions = $this->perPageOptions();
        $data = $request->validate([
            'q'         => ['nullable','string','max:100'],
            'period_id' => ['nullable','integer','exists:assessment_periods,id'],
            'published' => ['nullable','in:yes,no'],
            'per_page'  => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);

        $q        = $data['q'] ?? null;
        $periodId = $data['period_id'] ?? null;
        $published= $data['published'] ?? null;
        $perPage  = (int)($data['per_page'] ?? 12);

        $items = Allocation::query()
            ->with(['period:id,name,start_date,end_date', 'unit:id,name'])
            ->when($q, function ($w) use ($q) {
                $w->whereHas('unit', fn($u) => $u->where('name','like',"%{$q}%"))
                  ->orWhereHas('period', fn($p) => $p->where('name','like',"%{$q}%"));
            })
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($published === 'yes', fn($w) => $w->whereNotNull('published_at'))
            ->when($published === 'no', fn($w) => $w->whereNull('published_at'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin_rs.unit_remuneration_allocations.index', [
            'items'           => $items,
            'periods'         => AssessmentPeriod::orderByDesc('start_date')->get(['id','name']),
            'perPage'         => $perPage,
            'perPageOptions'  => $perPageOptions,
            'filters'         => [
                'q'         => $q,
                'period_id' => $periodId,
                'published' => $published,
            ],
        ]);
    }

    /** Create form */
    public function create(): View
    {
        return view('admin_rs.unit_remuneration_allocations.create', [
            'item'    => new Allocation(['amount' => 0]),
            'periods' => AssessmentPeriod::orderByDesc('start_date')->get(['id','name']),
            'units'   => Unit::orderBy('name')->get(['id','name']),
        ]);
    }

    /** Store allocation */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['published_at'] = $request->boolean('publish_now') ? now() : null;
        Allocation::create($data);
        return redirect()->route('admin_rs.unit-remuneration-allocations.index')->with('status','Alokasi dibuat.');
    }

    /** Show isn't used - redirect to index */
    public function show(Allocation $allocation): RedirectResponse
    { return redirect()->route('admin_rs.unit-remuneration-allocations.index'); }

    /** Edit form */
    public function edit(Allocation $allocation): View
    {
        return view('admin_rs.unit_remuneration_allocations.edit', [
            'item'    => $allocation,
            'periods' => AssessmentPeriod::orderByDesc('start_date')->get(['id','name']),
            'units'   => Unit::orderBy('name')->get(['id','name']),
        ]);
    }

    /** Update or publish toggle */
    public function update(Request $request, Allocation $allocation): RedirectResponse
    {
        // Support both full update and quick publish toggle via hidden inputs
        if ($request->has('publish_toggle')) {
            $shouldPublish = (bool)$request->boolean('publish_toggle');
            $allocation->update(['published_at' => $shouldPublish ? now() : null]);
            return back()->with('status', $shouldPublish ? 'Dipublish.' : 'Diubah ke draft.');
        }

        $data = $this->validateData($request, isUpdate: true);
        $data['published_at'] = $request->boolean('publish_now') ? now() : null;
        $allocation->update($data);
        return redirect()->route('admin_rs.unit-remuneration-allocations.index')->with('status','Alokasi diperbarui.');
    }

    /** Delete allocation */
    public function destroy(Allocation $allocation): RedirectResponse
    {
        $allocation->delete();
        return back()->with('status','Alokasi dihapus.');
    }

    protected function validateData(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'unit_id'              => ['required','integer','exists:units,id'],
            'amount'               => ['required','numeric','min:0'],
            'note'                 => ['nullable','string','max:500'],
        ]);
    }
}
