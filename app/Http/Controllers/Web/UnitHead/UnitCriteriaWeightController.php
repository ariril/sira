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
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')->get();
        }
        $criteria = collect();
        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            $activePeriod = DB::table('assessment_periods')->where('status', 'active')->first();
        }
        $activePeriodId = $activePeriod?->id;
        if (Schema::hasTable('performance_criterias')) {
            // Sembunyikan kriteria yang sudah diajukan/aktif pada periode aktif untuk unit ini
            $usedIds = collect();
            if ($unitId && $activePeriodId && Schema::hasTable('unit_criteria_weights')) {
                $usedIds = DB::table('unit_criteria_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $activePeriodId)
                    ->whereIn('status', ['draft','pending','active'])
                    ->pluck('performance_criteria_id');
            }
            $criteria = DB::table('performance_criterias')
                ->where('is_active', true)
                ->when($usedIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $usedIds->all()))
                ->orderBy('name')->get();
        }

        // Listing weights for this unit (+ optional period)
        $currentTotal = 0; // total bobot draft/rejected yang akan diajukan massal
        if ($unitId && Schema::hasTable('unit_criteria_weights')) {
            $builder = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc', 'pc.id', '=', 'w.performance_criteria_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'w.assessment_period_id')
                ->selectRaw('w.id, w.weight, w.status, w.assessment_period_id, ap.name as period_name, pc.name as criteria_name, pc.type as criteria_type')
                ->where('w.unit_id', $unitId)
                ->orderBy('pc.name');
            if (!empty($periodId)) $builder->where('w.assessment_period_id', (int) $periodId);
            $items = $builder->paginate($perPage)->withQueryString();

            // Hitung total bobot (draft + rejected) untuk validasi 100%
            $sumQuery = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->whereIn('status', ['draft','rejected']);
            $targetPeriodId = !empty($periodId) ? (int)$periodId : ($activePeriodId ?? null);
            if (!empty($targetPeriodId)) $sumQuery->where('assessment_period_id', $targetPeriodId);
            $currentTotal = (float) $sumQuery->sum('weight');
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
            'currentTotal' => $currentTotal,
            'activePeriod' => $activePeriod,
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
        // Gunakan periode aktif jika tidak dikirim / abaikan input manual
        $activePeriodId = DB::table('assessment_periods')->where('status','active')->value('id');
        if (!$activePeriodId) {
            return back()->withErrors(['assessment_period_id' => 'Tidak ada periode aktif. Hubungi Admin RS.'])->withInput();
        }
        $data['assessment_period_id'] = $activePeriodId;
        // Ensure criteria is active
        $isActive = DB::table('performance_criterias')->where('id', $data['performance_criteria_id'])->value('is_active');
        if (!$isActive) return back()->withErrors(['performance_criteria_id' => 'Kriteria tidak aktif'])->withInput();

        // Cegah duplikasi untuk periode aktif (apapun status selain rejected)
        $exists = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('performance_criteria_id', $data['performance_criteria_id'])
            ->where('assessment_period_id', $data['assessment_period_id'])
            ->whereIn('status', ['draft','pending','active'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['performance_criteria_id' => 'Kriteria ini sudah diajukan/aktif pada periode berjalan.'])->withInput();
        }

        // Validasi total <= 100 (hanya draft+rejected yang dihitung sebagai paket pengajuan)
        $current = (float) DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $data['assessment_period_id'])
            ->whereIn('status', ['draft','rejected'])
            ->sum('weight');
        if (($current + (float)$data['weight']) > 100) {
            return back()->with('danger', 'Total bobot akan melebihi 100%. Kurangi nilai bobot.')->withInput();
        }

        DB::table('unit_criteria_weights')->insert([
            'unit_id'                 => $unitId,
            'performance_criteria_id' => $data['performance_criteria_id'],
            'assessment_period_id'    => $data['assessment_period_id'],
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
        // Validasi total tidak melebihi 100 saat update
        $othersSum = (float) DB::table('unit_criteria_weights')
            ->where('unit_id', $row->unit_id)
            ->where('assessment_period_id', $row->assessment_period_id)
            ->whereIn('status', ['draft','rejected'])
            ->where('id', '!=', $id)
            ->sum('weight');
        if (($othersSum + (float)$data['weight']) > 100) {
            return back()->with('danger', 'Total bobot akan melebihi 100%. Kurangi nilai bobot.');
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
        // Perbaikan enum: gunakan ->value
        if (!in_array($weight->status->value, ['draft','rejected'], true)) {
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

    /** Ajukan seluruh draft/rejected sekaligus bila total bobot = 100%. */
    public function submitAll(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me->unit_id;
        if (!$unitId) abort(403);

        $periodId = $request->integer('period_id'); // opsional
        $query = UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->whereIn('status', ['draft','rejected']);
        if ($periodId) $query->where('assessment_period_id', $periodId);
        $weights = $query->get();
        $total = (float) $weights->sum('weight');
        if ((int) round($total) !== 100) {
            return back()->withErrors(['total' => 'Total bobot saat ini '.number_format($total,2).'%. Harus tepat 100% sebelum pengajuan massal.']);
        }
        foreach ($weights as $w) {
            $w->status = 'pending';
            $w->unit_head_id = $me->id;
            if (empty($w->unit_head_note)) $w->unit_head_note = 'Pengajuan massal';
            $w->save();
        }
        return back()->with('status', 'Seluruh bobot diajukan untuk persetujuan.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }
}
