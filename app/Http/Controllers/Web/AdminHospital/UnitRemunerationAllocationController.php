<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\AssessmentPeriod;
use App\Models\Unit;
use App\Models\Profession;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

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
            ->select([
                DB::raw('MIN(id) as id'),
                'assessment_period_id',
                'unit_id',
                DB::raw('SUM(amount) as amount'),
                DB::raw('MAX(published_at) as published_at'),
                DB::raw('MAX(created_at) as created_at'),
                DB::raw('MAX(updated_at) as updated_at'),
            ])
            ->with(['period:id,name,start_date,end_date', 'unit:id,name'])
            ->when($q, function ($w) use ($q) {
                $w->whereHas('unit', fn($u) => $u->where('name','like',"%{$q}%"))
                  ->orWhereHas('period', fn($p) => $p->where('name','like',"%{$q}%"));
            })
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($published === 'yes', fn($w) => $w->whereNotNull('published_at'))
            ->when($published === 'no', fn($w) => $w->whereNull('published_at'))
            ->groupBy('assessment_period_id','unit_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
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
        $unitProfessionMap = DB::table('users')
            ->whereNotNull('profession_id')
            ->select('unit_id','profession_id')
            ->distinct()
            ->get()
            ->groupBy('unit_id');

        return view('admin_rs.unit_remuneration_allocations.create', [
            'item'    => new Allocation(['amount' => 0]),
            'periods' => AssessmentPeriod::orderByDesc('start_date')->get(['id','name']),
            'units'   => Unit::orderBy('name')->get(['id','name']),
            'professions' => Profession::orderBy('name')->get(['id','name']),
            'unitProfessionMap' => $unitProfessionMap,
        ]);
    }

    /** Store allocation */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $lineAmounts = $this->validatedLines($request);

        if ($this->groupPublished($data['assessment_period_id'], $data['unit_id'])) {
            return back()->withInput()->with('danger', 'Alokasi sudah dipublish dan tidak dapat diubah.');
        }

        $totalAmount = array_sum($lineAmounts);
        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Isi nominal untuk minimal satu profesi.',
            ]);
        }

        // Friendly duplicate check to avoid DB exception on unique constraint
        $publishedAt = $request->boolean('publish_now') ? now() : null;

        // Replace existing group (period + unit) with new rows
        Allocation::query()
            ->where('assessment_period_id', $data['assessment_period_id'])
            ->where('unit_id', $data['unit_id'])
            ->delete();

        foreach ($lineAmounts as $profId => $amount) {
            Allocation::create(array_merge($data, [
                'profession_id' => $profId,
                'amount' => $amount,
                'published_at' => $publishedAt,
            ]));
        }
        return redirect()->route('admin_rs.unit-remuneration-allocations.index')->with('status','Alokasi dibuat.');
    }

    /** Show isn't used - redirect to index */
    public function show(Allocation $allocation): RedirectResponse
    { return redirect()->route('admin_rs.unit-remuneration-allocations.index'); }

    /** Edit form */
    public function edit(Allocation $allocation): View
    {
        $group = Allocation::query()
            ->where('assessment_period_id', $allocation->assessment_period_id)
            ->where('unit_id', $allocation->unit_id)
            ->get();

        $lineMap = $group->whereNotNull('profession_id')->pluck('amount','profession_id');
        $allocation->amount = $group->sum('amount');
        $allocation->published_at = $group->max('published_at');

        return view('admin_rs.unit_remuneration_allocations.edit', [
            'item'    => $allocation,
            'periods' => AssessmentPeriod::orderByDesc('start_date')->get(['id','name']),
            'units'   => Unit::orderBy('name')->get(['id','name']),
            'professions' => Profession::orderBy('name')->get(['id','name']),
            'unitProfessionMap' => $this->unitProfessions(),
            'lineMap' => $lineMap,
        ]);
    }

    /** Update or publish toggle */
    public function update(Request $request, Allocation $allocation): RedirectResponse
    {
        if ($this->groupPublished($allocation->assessment_period_id, $allocation->unit_id)) {
            return back()->with('danger', 'Alokasi sudah dipublish dan tidak dapat diubah.');
        }

        // Support both full update and quick publish toggle via hidden inputs for draft entries
        if ($request->has('publish_toggle')) {
            $shouldPublish = (bool)$request->boolean('publish_toggle');
            Allocation::query()
                ->where('assessment_period_id', $allocation->assessment_period_id)
                ->where('unit_id', $allocation->unit_id)
                ->update(['published_at' => $shouldPublish ? now() : null]);
            return back()->with('status', $shouldPublish ? 'Dipublish.' : 'Diubah ke draft.');
        }

        $data = $this->validateData($request, isUpdate: true);
        $lineAmounts = $this->validatedLines($request);
        $publishedAt = $request->boolean('publish_now') ? now() : null;

        if ($this->groupPublished($data['assessment_period_id'], $data['unit_id'])) {
            return back()->withInput()->with('danger', 'Alokasi sudah dipublish dan tidak dapat diubah.');
        }

        $totalAmount = array_sum($lineAmounts);
        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Isi nominal untuk minimal satu profesi.',
            ]);
        }

        // Replace group
        Allocation::query()
            ->where('assessment_period_id', $allocation->assessment_period_id)
            ->where('unit_id', $allocation->unit_id)
            ->delete();

        foreach ($lineAmounts as $profId => $amount) {
            Allocation::create(array_merge($data, [
                'profession_id' => $profId,
                'amount' => $amount,
                'published_at' => $publishedAt,
            ]));
        }
        return redirect()->route('admin_rs.unit-remuneration-allocations.index')->with('status','Alokasi diperbarui.');
    }

    /** Delete allocation */
    public function destroy(Allocation $allocation): RedirectResponse
    {
        if ($this->groupPublished($allocation->assessment_period_id, $allocation->unit_id)) {
            return back()->with('danger', 'Alokasi sudah dipublish dan tidak dapat diubah.');
        }

        Allocation::query()
            ->where('assessment_period_id', $allocation->assessment_period_id)
            ->where('unit_id', $allocation->unit_id)
            ->delete();
        return back()->with('status','Alokasi dihapus.');
    }

    protected function validateData(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'unit_id'              => ['required','integer','exists:units,id'],
            'note'                 => ['nullable','string','max:500'],
        ], [
            'assessment_period_id.required' => 'Anda wajib memilih periode.',
        ]);
    }

    protected function unitProfessions()
    {
        return DB::table('users')
            ->whereNotNull('profession_id')
            ->select('unit_id','profession_id')
            ->distinct()
            ->get()
            ->groupBy('unit_id');
    }

    /**
     * @return array<int,float> profession_id => amount
     */
    protected function validatedLines(Request $request): array
    {
        $raw = $request->input('lines', []);
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $profId => $val) {
            if ($val === null || $val === '') continue;
            $num = (float)$val;
            if ($num < 0) continue;
            $out[(int)$profId] = $num;
        }
        return $out;
    }

    protected function groupPublished(int $periodId, int $unitId): bool
    {
        return Allocation::query()
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->whereNotNull('published_at')
            ->exists();
    }
}
