<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\UnitCriteriaWeight;
use App\Models\PerformanceCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UnitCriteriaWeightController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;

        // Filters
        $perPageOptions = [10, 20, 30, 50];
        $data = $request->validate([
            'period_id' => ['nullable','integer'],
            'per_page'  => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);
        $periodId = $data['period_id'] ?? null;
        $perPage  = (int) ($data['per_page'] ?? 20);

        // Options: periods and criteria
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc('is_active')->orderByDesc('id')->get();
        }
        $criteria = collect();
        if (Schema::hasTable('performance_criterias')) {
            $criteria = DB::table('performance_criterias')
                ->where('is_active', true)->orderBy('name')->get();
        }

        // Listing weights for this unit (+ optional period)
        if ($unitId && Schema::hasTable('unit_criteria_weights')) {
            $builder = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc', 'pc.id', '=', 'w.performance_criteria_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'w.assessment_period_id')
                ->selectRaw('w.id, w.weight, w.status, w.assessment_period_id, ap.name as period_name, pc.name as criteria_name, pc.type as criteria_type')
                ->where('w.unit_id', $unitId)
                ->orderBy('pc.name');
            if (!empty($periodId)) $builder->where('w.assessment_period_id', (int) $periodId);
            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->integer('page', 1),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        return view('kepala_unit.unit_criteria_weights.index', [
            'items'     => $items,
            'periods'   => $periods,
            'criteria'  => $criteria,
            'periodId'  => $periodId,
            'perPage'   => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Redirect to index; we use inline form there
        return $this->index(request());
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
            'assessment_period_id'    => ['nullable','integer','exists:assessment_periods,id'],
            'performance_criteria_id' => ['required','integer','exists:performance_criterias,id'],
            'weight'                  => ['required','numeric','min:0','max:100'],
        ]);
        // Ensure criteria is active
        $isActive = DB::table('performance_criterias')->where('id', $data['performance_criteria_id'])->value('is_active');
        if (!$isActive) return back()->withErrors(['performance_criteria_id' => 'Kriteria tidak aktif'])->withInput();

        // Create draft
        $exists = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('performance_criteria_id', $data['performance_criteria_id'])
            ->where('assessment_period_id', $data['assessment_period_id'] ?? null)
            ->where('status', 'draft')
            ->exists();
        if ($exists) {
            return back()->withErrors(['performance_criteria_id' => 'Sudah ada draft untuk kriteria & periode tersebut.'])->withInput();
        }

        DB::table('unit_criteria_weights')->insert([
            'unit_id'                 => $unitId,
            'performance_criteria_id' => $data['performance_criteria_id'],
            'assessment_period_id'    => $data['assessment_period_id'] ?? null,
            'weight'                  => $data['weight'],
            'status'                  => 'draft',
            'unit_head_id'            => $me->id,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
        return back()->with('status', 'Bobot ditambahkan sebagai draft.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): RedirectResponse { return back(); }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): RedirectResponse { return back(); }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $data = $request->validate([
            'weight' => ['required','numeric','min:0','max:100'],
        ]);
        $row = DB::table('unit_criteria_weights')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        if (!in_array((string)$row->status, ['draft','rejected'], true)) {
            return back()->withErrors(['status' => 'Hanya draft/ditolak yang bisa diedit.']);
        }
        DB::table('unit_criteria_weights')->where('id', $id)->update([
            'weight' => $data['weight'],
            'updated_at' => now(),
        ]);
        return back()->with('status', 'Bobot diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $row = DB::table('unit_criteria_weights')->where('id', $id)->first();
        if (!$row) abort(404);
        if ((int)$row->unit_id !== (int)$me->unit_id) abort(403);
        if (!in_array((string)$row->status, ['draft','rejected'], true)) {
            return back()->withErrors(['status' => 'Hanya draft/ditolak yang bisa dihapus.']);
        }
        DB::table('unit_criteria_weights')->where('id', $id)->delete();
        return back()->with('status', 'Bobot dihapus.');
    }

    /** Submit draft weight for approval (pending). */
    public function submitForApproval(Request $request, UnitCriteriaWeight $weight): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        if ((int)$weight->unit_id !== (int)$me->unit_id) abort(403);
        if (!in_array((string)$weight->status, ['draft','rejected'], true)) {
            return back()->withErrors(['status' => 'Hanya draft/ditolak yang bisa diajukan.']);
        }
        $note = (string) $request->input('unit_head_note');
        $weight->update([
            'status' => 'pending',
            'unit_head_id' => $me->id,
            'unit_head_note' => $note,
        ]);
        return back()->with('status', 'Diajukan untuk persetujuan.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }
}
