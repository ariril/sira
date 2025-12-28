<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\UnitCriteriaWeight;
use App\Models\PerformanceCriteria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Services\RaterWeightGenerator;
use Illuminate\Validation\Rule;
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
        $rawFilters = [
            'period_id' => $request->input('period_id'),
            'per_page'  => $request->input('per_page'),
        ];

        if (array_key_exists('period_id', $rawFilters) && $rawFilters['period_id'] === '') {
            $rawFilters['period_id'] = null;
        }
        if (array_key_exists('per_page', $rawFilters) && $rawFilters['per_page'] === '') {
            $rawFilters['per_page'] = null;
        }

        $data = Validator::make($rawFilters, [
            'period_id' => ['nullable', 'integer'],
            'per_page'  => ['nullable', 'integer', Rule::in($perPageOptions)],
        ])->validate();

        $periodId = null;
        if (array_key_exists('period_id', $data) && $data['period_id'] !== null) {
            $periodId = (int) $data['period_id'];
        }
        $perPage  = (int) ($data['per_page'] ?? 20);

        // Options: periods and criteria
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = '" . AssessmentPeriod::STATUS_ACTIVE . "'"))
                ->orderByDesc('id')->get();
        }
        $criteria = collect();
        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            $activePeriod = DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->first();
        }
        $activePeriodId = $activePeriod?->id;
        $this->archiveNonActivePeriods($unitId, $activePeriodId);
        $previousPeriod = $this->previousPeriod($activePeriod, $unitId);
        $targetPeriodId = !empty($periodId) ? (int) $periodId : ($activePeriodId ?? null);
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
        $pendingTotal = 0; // total bobot yang sedang menunggu persetujuan
        $pendingCount = 0;
        $committedTotal = 0; // total bobot active+pending
        $requiredTotal = 100; // sisa bobot yang harus diajukan
        $activeTotal = 0; // total bobot aktif
        $hasDraftOrRejected = false;
        $itemsWorking = collect();
        $itemsHistory = collect();
        if ($unitId && Schema::hasTable('unit_criteria_weights')) {
            $baseBuilder = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc', 'pc.id', '=', 'w.performance_criteria_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'w.assessment_period_id')
                ->selectRaw('w.id, w.weight, w.status, w.assessment_period_id, ap.name as period_name, pc.name as criteria_name, pc.type as criteria_type')
                ->where('w.unit_id', $unitId);
            if (!empty($periodId)) $baseBuilder->where('w.assessment_period_id', (int) $periodId);

            $itemsWorking = (clone $baseBuilder)
                ->where('w.status','!=','archived')
                ->orderByDesc('w.assessment_period_id')
                ->orderBy('pc.name')
                ->get();

            $itemsHistory = (clone $baseBuilder)
                ->where('w.status','archived')
                ->orderByDesc('w.assessment_period_id')
                ->orderBy('pc.name')
                ->get();

            // Hitung total bobot (draft + rejected) untuk validasi 100%
            $sumQuery = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->whereIn('status', ['draft','rejected']);
            if (!empty($targetPeriodId)) $sumQuery->where('assessment_period_id', $targetPeriodId);
            $currentTotal = (float) $sumQuery->sum('weight');

            $pendingQuery = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('status', 'pending');
            if (!empty($targetPeriodId)) $pendingQuery->where('assessment_period_id', $targetPeriodId);
            $pendingCount = (int) $pendingQuery->count();
            $pendingTotal = (float) $pendingQuery->sum('weight');

            $committedQuery = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->whereIn('status', ['pending','active']);
            if (!empty($targetPeriodId)) $committedQuery->where('assessment_period_id', $targetPeriodId);
            $committedTotal = (float) $committedQuery->sum('weight');
            $requiredTotal = max(0, 100 - $committedTotal);

            $activeQuery = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('status', 'active');
            if (!empty($targetPeriodId)) $activeQuery->where('assessment_period_id', $targetPeriodId);
            $activeTotal = (float) $activeQuery->sum('weight');

            $hasDraftOrRejected = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->whereIn('status', ['draft','rejected'])
                ->when(!empty($targetPeriodId), fn($q) => $q->where('assessment_period_id', $targetPeriodId))
                ->exists();
        } else {
            $itemsWorking = collect();
            $itemsHistory = collect();
        }

        return view('kepala_unit.unit_criteria_weights.index', [
            'itemsWorking'     => $itemsWorking,
            'itemsHistory'     => $itemsHistory,
            'periods'   => $periods,
            'criteria'  => $criteria,
            'periodId'  => $periodId,
            'perPage'   => $perPage,
            'perPageOptions' => $perPageOptions,
            'currentTotal' => $currentTotal,
            'activePeriod' => $activePeriod,
            'committedTotal' => $committedTotal,
            'requiredTotal' => $requiredTotal,
            'pendingCount' => $pendingCount,
            'pendingTotal' => $pendingTotal,
            'targetPeriodId' => $targetPeriodId,
            'activeTotal' => $activeTotal,
            'hasDraftOrRejected' => $hasDraftOrRejected,
            'previousPeriod' => $previousPeriod,
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
        $activePeriodId = DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->value('id');
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

        $extraStatus = null;
        if (
            Schema::hasTable('criteria_rater_rules') &&
            Schema::hasTable('unit_rater_weights') &&
            Schema::hasTable('users') &&
            Schema::hasTable('performance_criterias')
        ) {
            $criteria = DB::table('performance_criterias')
                ->select('id', 'name', 'is_360')
                ->where('id', $data['performance_criteria_id'])
                ->first();

            if ($criteria && (bool) $criteria->is_360) {
                $assessorTypes = DB::table('criteria_rater_rules')
                    ->where('performance_criteria_id', (int) $criteria->id)
                    ->distinct()
                    ->pluck('assessor_type')
                    ->filter(fn($v) => !empty($v))
                    ->values();

                if ($assessorTypes->count() === 1) {
                    $assessorType = (string) $assessorTypes->first();

                    $sync = app(\App\Services\RaterWeightGenerator::class)->syncForUnitPeriod($unitId, (int) $data['assessment_period_id']);
                    $created = (int) ($sync['created'] ?? 0);
                    if ($created > 0) {
                        $extraStatus = "Aturan kriteria 360 '{$criteria->name}' hanya memiliki 1 tipe penilai ('{$assessorType}'). Sistem menyinkronkan draft Bobot Penilai 360 (dibuat {$created} baris baru).";
                    }
                }
            }
        }

        $message = 'Bobot ditambahkan sebagai draft.';
        if (!empty($extraStatus)) {
            $message .= ' ' . $extraStatus;
        }

        return back()->with('status', $message);
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

        $periodId = $request->integer('period_id') ?: DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->value('id');
        if (!$periodId) {
            return back()->withErrors(['period_id' => 'Tidak ada periode aktif untuk diajukan.']);
        }

        $query = UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', ['draft','rejected']);
        $weights = $query->get();
        $total = (float) $weights->sum('weight');

        $committed = (float) UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', ['pending','active'])
            ->sum('weight');
        $required = max(0, 100 - $committed);
        if ($required <= 0) {
            return back()->withErrors(['total' => 'Semua bobot untuk periode ini sudah 100%.']);
        }
        if ((int) round($total) !== (int) round($required)) {
            return back()->withErrors(['total' => 'Draft siap diajukan '.number_format($total,2).'%, sedangkan kebutuhan tersisa '.number_format($required,2).'%. Sesuaikan agar sama.']);
        }
        foreach ($weights as $w) {
            $w->status = 'pending';
            $w->unit_head_id = $me->id;
            if (empty($w->unit_head_note)) $w->unit_head_note = 'Pengajuan massal';
            $w->save();
        }

        // If submitted list contains any 360 criteria, remind and auto-generate rater weights.
        $submittedCriteriaIds = $weights->pluck('performance_criteria_id')->map(fn($v) => (int) $v)->filter()->values()->all();
        $has360 = false;
        if (!empty($submittedCriteriaIds) && Schema::hasTable('performance_criterias')) {
            $has360 = DB::table('performance_criterias')
                ->whereIn('id', $submittedCriteriaIds)
                ->where('is_360', true)
                ->exists();
        }

        if ($has360) {
            app(RaterWeightGenerator::class)->syncForUnitPeriod((int) $unitId, (int) $periodId);

            return back()
                ->with('status', 'Seluruh bobot diajukan untuk persetujuan.')
                ->with('warning_360_message', 'Bobot penilaian 360 perlu diatur')
                ->with('warning_360_url', route('kepala_unit.rater_weights.index', ['assessment_period_id' => (int) $periodId]));
        }

        return back()->with('status', 'Seluruh bobot diajukan untuk persetujuan.');
    }

    /**
     * Create draft copies from current active weights to request mid-period changes.
     */
    public function requestChange(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;
        if (!$unitId) abort(403);

        $periodId = $request->integer('period_id') ?: DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->value('id');
        if (!$periodId) {
            return back()->withErrors(['period_id' => 'Tidak ada periode aktif untuk diajukan ulang.']);
        }

        $activeRows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->where('status', 'active')
            ->get();

        if ($activeRows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot aktif yang bisa diajukan ulang.']);
        }

        $activeTotal = (float) $activeRows->sum('weight');
        if ((int) round($activeTotal) !== 100) {
            return back()->withErrors(['status' => 'Total bobot aktif belum 100%. Tidak dapat mengajukan perubahan.']);
        }

        $hasPendingOrDraft = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', ['draft','pending','rejected'])
            ->exists();
        if ($hasPendingOrDraft) {
            return back()->withErrors(['status' => 'Masih ada bobot draft/pending. Selesaikan terlebih dahulu sebelum mengajukan perubahan.']);
        }

        DB::transaction(function () use ($activeRows, $me, $periodId) {
            foreach ($activeRows as $row) {
                DB::table('unit_criteria_weights')->where('id', $row->id)->update([
                    'status' => 'archived',
                    'updated_at' => now(),
                ]);

                DB::table('unit_criteria_weights')->insert([
                    'unit_id' => $row->unit_id,
                    'performance_criteria_id' => $row->performance_criteria_id,
                    'assessment_period_id' => $row->assessment_period_id,
                    'weight' => $row->weight,
                    'status' => 'draft',
                    'unit_head_id' => $me->id,
                    'unit_head_note' => 'Pengajuan perubahan tengah periode',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Perubahan diajukan. Bobot baru dibuat sebagai draft.');
    }

    /** Salin bobot aktif periode sebelumnya menjadi draft periode aktif. */
    public function copyFromPrevious(): RedirectResponse
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;
        if (!$unitId) abort(403);

        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_criteria_weights')) {
            return back()->withErrors(['status' => 'Tabel periode atau bobot belum tersedia.']);
        }

        $activePeriod = DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->first();
        if (!$activePeriod) return back()->withErrors(['status' => 'Tidak ada periode aktif.']);

        $previousPeriod = $this->previousPeriod($activePeriod, $unitId);
        if (!$previousPeriod) return back()->withErrors(['status' => 'Tidak ada periode sebelumnya untuk disalin.']);

        $alreadyExists = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $activePeriod->id)
            ->where('status', '!=', 'archived')
            ->exists();
        if ($alreadyExists) {
            return back()->withErrors(['status' => 'Periode aktif sudah memiliki bobot. Hapus atau arsipkan terlebih dahulu.']);
        }

        $sourceRows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $previousPeriod->id)
            ->where('status', 'active')
            ->get();

        if ($sourceRows->isEmpty()) {
            // Fallback untuk periode yang sudah diarsip otomatis tapi sebelumnya aktif
            $sourceRows = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $previousPeriod->id)
                ->where('status', 'archived')
                ->get();
        }

        if ($sourceRows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot aktif pada periode sebelumnya.']);
        }

        DB::transaction(function () use ($sourceRows, $me, $activePeriod) {
            foreach ($sourceRows as $row) {
                DB::table('unit_criteria_weights')->insert([
                    'unit_id' => $row->unit_id,
                    'performance_criteria_id' => $row->performance_criteria_id,
                    'assessment_period_id' => $activePeriod->id,
                    'weight' => $row->weight,
                    'status' => 'draft',
                    'unit_head_id' => $me->id,
                    'unit_head_note' => 'Salinan dari periode sebelumnya',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Bobot periode sebelumnya disalin sebagai draft.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }

    private function archiveNonActivePeriods(?int $unitId, ?int $activePeriodId): void
    {
        if (!$unitId || !$activePeriodId) return;
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_criteria_weights')) return;

        DB::table('unit_criteria_weights')
            ->join('assessment_periods as ap', 'ap.id', '=', 'unit_criteria_weights.assessment_period_id')
            ->where('unit_criteria_weights.unit_id', $unitId)
            ->where('unit_criteria_weights.status', '!=', 'archived')
            ->where('unit_criteria_weights.assessment_period_id', '!=', $activePeriodId)
            ->where('ap.status', '!=', 'active')
            ->update([
                'unit_criteria_weights.status' => 'archived',
                'unit_criteria_weights.updated_at' => now(),
            ]);
    }

    private function previousPeriod($activePeriod, ?int $unitId)
    {
        if (!$activePeriod) return null;
        if (!Schema::hasTable('assessment_periods')) return null;

        $periodStatuses = ['active','locked','approval','closed'];

        $query = DB::table('assessment_periods')
            ->where('id', '!=', $activePeriod->id)
            ->whereIn('status', $periodStatuses);

        if (Schema::hasColumn('assessment_periods', 'start_date') && $activePeriod->start_date) {
            $query->where('start_date', '<', $activePeriod->start_date)
                ->orderByDesc('start_date');
        } else {
            $query->where('id', '<', $activePeriod->id)
                ->orderByDesc('id');
        }

        $candidate = $query->orderByDesc('id')->first();
        if (!$candidate) return null;
        if (!$unitId || !Schema::hasTable('unit_criteria_weights')) return $candidate;

        $hasWeights = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $candidate->id)
            ->whereIn('status', ['active','archived'])
            ->exists();

        if ($hasWeights) return $candidate;

        // Cari periode sebelumnya yang memiliki bobot aktif/arsip
        return DB::table('assessment_periods')
            ->where('id', '!=', $activePeriod->id)
            ->whereIn('status', $periodStatuses)
            ->where('id', '<', $candidate->id)
            ->orderByDesc('id')
            ->whereExists(function($sub) use ($unitId) {
                $sub->select(DB::raw(1))
                    ->from('unit_criteria_weights')
                    ->whereColumn('unit_criteria_weights.assessment_period_id', 'assessment_periods.id')
                    ->where('unit_criteria_weights.unit_id', $unitId)
                    ->whereIn('unit_criteria_weights.status', ['active','archived']);
            })
            ->first();
    }
}
