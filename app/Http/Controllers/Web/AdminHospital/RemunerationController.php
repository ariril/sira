<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Remuneration;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\UnitCriteriaWeight;
use App\Enums\PerformanceCriteriaType;
use App\Enums\RemunerationPaymentStatus;
use App\Enums\AssessmentValidationStatus;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use App\Support\ProportionalAllocator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    /**
     * Purpose: Calculation workspace. Pick a period, run calculation,
     * and review summary of remunerations generated for that period.
     */
    public function calcIndex(Request $request): View
    {
        $periodId = (int) $request->integer('period_id');
        $periods  = AssessmentPeriod::orderByDesc('start_date')->get(['id','name','start_date']);

        $summary = null;
        $allocSummary = null;
        $selectedPeriod = null;
        $prerequisites = null;
        $canRun = false;
        $needsRecalcConfirm = false;
        $lastCalculated = null;
        $remunerations = collect();
        if ($periodId) {
            $selectedPeriod = AssessmentPeriod::find($periodId);

            $remunerations = Remuneration::with('user:id,name,unit_id')
                ->where('assessment_period_id', $periodId)
                ->orderBy('user_id')
                ->get();
            $summary = [
                'count' => $remunerations->count(),
                'total' => (float) $remunerations->sum('amount'),
            ];
            $allocs = Allocation::where('assessment_period_id', $periodId)->whereNotNull('published_at')->get(['id','amount']);
            $allocSummary = [
                'count' => $allocs->count(),
                'total' => (float) $allocs->sum('amount'),
            ];

            // Prerequisites
            $isLocked = $selectedPeriod && in_array($selectedPeriod->status, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true);

            $assessmentCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->count();
            $validatedCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->where('validation_status', AssessmentValidationStatus::VALIDATED->value)
                ->count();
            $allValidated = $assessmentCount > 0 && $assessmentCount === $validatedCount;

            $allocTotal = (int) Allocation::query()->where('assessment_period_id', $periodId)->count();
            $allocPublished = (int) Allocation::query()->where('assessment_period_id', $periodId)->whereNotNull('published_at')->count();
            $allAllocPublished = $allocTotal > 0 && $allocTotal === $allocPublished;

            $prerequisites = [
                [
                    'label' => 'Periode status = LOCKED',
                    'ok' => $isLocked,
                    'detail' => $selectedPeriod ? ('Status saat ini: ' . strtoupper((string) $selectedPeriod->status)) : null,
                ],
                [
                    'label' => 'Semua penilaian sudah tervalidasi final',
                    'ok' => $allValidated,
                    'detail' => $assessmentCount > 0
                        ? ("Tervalidasi: {$validatedCount} / {$assessmentCount}")
                        : 'Belum ada data penilaian pada periode ini.',
                ],
                [
                    'label' => 'Semua alokasi remunerasi unit sudah dipublish',
                    'ok' => $allAllocPublished,
                    'detail' => $allocTotal > 0
                        ? ("Published: {$allocPublished} / {$allocTotal}")
                        : 'Belum ada alokasi pada periode ini.',
                ],
            ];

            $canRun = $isLocked && $allValidated && $allAllocPublished;
            $needsRecalcConfirm = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->whereNull('published_at')
                ->exists();

            $lastCalculated = Remuneration::query()
                ->with('revisedBy:id,name')
                ->where('assessment_period_id', $periodId)
                ->whereNotNull('calculated_at')
                ->orderByDesc('calculated_at')
                ->first(['id','calculated_at','revised_by']);
        }

        return view('admin_rs.remunerations.calc_index', [
            'periods'       => $periods,
            'selectedId'    => $periodId ?: null,
            'selectedPeriod'=> $selectedPeriod,
            'remunerations' => $remunerations,
            'summary'       => $summary,
            'allocSummary'  => $allocSummary,
            'prerequisites' => $prerequisites,
            'canRun'        => $canRun,
            'needsRecalcConfirm' => $needsRecalcConfirm,
            'lastCalculated' => $lastCalculated,
        ]);
    }

    /**
        * Run remuneration calculation for a given period using configured WSM scores.
        * Each published unit allocation is distributed proporsional to skor WSM
        * (sumber: performance_assessments.total_wsm_score). Tetap menambahkan bonus
        * tugas tambahan yang disetujui sebagai penyesuaian akhir.
     */
        public function runCalculation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required','integer','exists:assessment_periods,id'],
        ]);
        $periodId = (int) $data['period_id'];
        $period = AssessmentPeriod::findOrFail($periodId);

        // Hard gate: do not allow calculation while period is ACTIVE/DRAFT
        $isLocked = in_array($period->status, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true);
        if (!$isLocked) {
            return back()->with('danger', 'Perhitungan hanya bisa dijalankan setelah periode ditutup (LOCKED).');
        }

        // Hard gate: all assessments must be finally validated
        $assessmentCount = (int) PerformanceAssessment::query()->where('assessment_period_id', $periodId)->count();
        $validatedCount = (int) PerformanceAssessment::query()
            ->where('assessment_period_id', $periodId)
            ->where('validation_status', AssessmentValidationStatus::VALIDATED->value)
            ->count();
        if ($assessmentCount === 0 || $assessmentCount !== $validatedCount) {
            return back()->with('danger', 'Tidak dapat menjalankan perhitungan: masih ada penilaian yang belum tervalidasi final.');
        }

        // Hard gate: all allocations must be published (not partial)
        $allocTotal = (int) Allocation::query()->where('assessment_period_id', $periodId)->count();
        $allocPublished = (int) Allocation::query()->where('assessment_period_id', $periodId)->whereNotNull('published_at')->count();
        if ($allocTotal === 0 || $allocTotal !== $allocPublished) {
            return back()->with('danger', 'Tidak dapat menjalankan perhitungan: alokasi remunerasi unit belum dipublish semua.');
        }

        $allocations = Allocation::with(['unit:id,name'])
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('published_at')
            ->get();

        $actorId = (int) Auth::id();
        DB::transaction(function () use ($allocations, $period, $periodId, $actorId) {
            foreach ($allocations as $alloc) {
                $this->distributeAllocation(
                    $alloc,
                    $period,
                    $periodId,
                    $alloc->profession_id,
                    (float) $alloc->amount,
                    $actorId
                );
            }

            // Apply penalty dari klaim batal terlambat (potong remunerasi) - idempotent
            $this->applyCancelledClaimPenalties($periodId);
        });

        return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
            ->with('status', 'Perhitungan selesai (berdasarkan skor kinerja terkonfigurasi).');
    }

    /**
     * Apply penalty dari klaim tugas tambahan yang dibatalkan setelah deadline.
     * - Claim menjadi sumber kebenaran penalty_applied/amount (sekali per claim)
     * - Remuneration draft (published_at = null) dikurangi total penalty per user
     */
    private function applyCancelledClaimPenalties(int $periodId): void
    {
        // Ambil gross remunerasi draft sebelum penalty (basis hitung % remuneration)
        $grossByUser = Remuneration::query()
            ->where('assessment_period_id', $periodId)
            ->whereNull('published_at')
            ->pluck('amount', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        if (empty($grossByUser)) {
            return;
        }

        $claims = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->selectRaw('c.id, c.user_id, c.penalty_type, c.penalty_value, c.penalty_base, c.awarded_bonus_amount, c.penalty_applied, c.penalty_amount, c.penalty_applied_at')
            ->where('t.assessment_period_id', $periodId)
            ->where('c.status', 'cancelled')
            ->where('c.is_violation', 1)
            ->get();

        if ($claims->isEmpty()) {
            return;
        }

        $now = now();
        $sumPenaltyByUser = [];

        foreach ($claims as $c) {
            $userId = (int) $c->user_id;
            $penaltyAmount = $c->penalty_amount !== null ? (float) $c->penalty_amount : null;
            $alreadyApplied = (bool) $c->penalty_applied;

            // Hitung penalty hanya jika belum pernah dihitung/ditandai
            if ($penaltyAmount === null || !$alreadyApplied) {
                $type = (string) ($c->penalty_type ?? 'none');
                $value = (float) ($c->penalty_value ?? 0);
                $base = (string) ($c->penalty_base ?? 'task_bonus');

                $computed = 0.0;
                if ($type === 'amount') {
                    $computed = $value;
                } elseif ($type === 'percent') {
                    $pct = max(0.0, min(100.0, $value));
                    $baseAmount = 0.0;
                    if ($base === 'remuneration') {
                        $baseAmount = (float) ($grossByUser[$userId] ?? 0);
                    } else {
                        $baseAmount = (float) ($c->awarded_bonus_amount ?? 0);
                    }
                    $computed = ($pct / 100.0) * $baseAmount;
                }

                $penaltyAmount = round(max(0.0, $computed), 2);

                DB::table('additional_task_claims')
                    ->where('id', (int) $c->id)
                    ->update([
                        'penalty_amount' => $penaltyAmount,
                        'penalty_applied' => true,
                        'penalty_applied_at' => $c->penalty_applied_at ?: $now,
                        'penalty_note' => 'Cancel after deadline',
                        'updated_at' => $now,
                    ]);
            }

            $sumPenaltyByUser[$userId] = ($sumPenaltyByUser[$userId] ?? 0.0) + (float) $penaltyAmount;
        }

        foreach ($sumPenaltyByUser as $userId => $sumPenalty) {
            $sumPenalty = round(max(0.0, (float) $sumPenalty), 2);
            if ($sumPenalty <= 0) continue;

            $rem = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->where('user_id', (int) $userId)
                ->whereNull('published_at')
                ->first();

            if (!$rem) continue;

            $gross = (float) ($grossByUser[(int) $userId] ?? $rem->amount);
            $net = max(0.0, round($gross - $sumPenalty, 2));
            $rem->amount = $net;
            $rem->calculated_at = now();

            $details = $rem->calculation_details ?? [];
            $details['penalty'] = [
                'source' => 'additional_task_claims(cancelled_after_deadline)',
                'total' => $sumPenalty,
            ];
            $rem->calculation_details = $details;

            $rem->save();
        }
    }

    private function distributeAllocation(Allocation $alloc, $period, int $periodId, ?int $professionId = null, ?float $overrideAmount = null, ?int $actorId = null): void
    {
        $users = User::query()
            ->where('unit_id', $alloc->unit_id)
            ->when($professionId, fn($q) => $q->where('profession_id', $professionId))
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->pluck('id');

        if ($users->isEmpty()) {
            return;
        }

        $amount = $overrideAmount ?? (float) $alloc->amount;
        if ($amount <= 0) {
            return;
        }

        // Ambil WSM yang sudah dihitung di performance_assessments (per periode) dan distribusikan proporsional di profesi + unit yang sama.
        $wsmTotals = DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->whereIn('user_id', $users)
            ->pluck('total_wsm_score', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();

        $unitTotal = array_sum($wsmTotals);
        if ($unitTotal <= 0) {
            $unitTotal = max(count($wsmTotals), 1);
            $wsmTotals = array_fill_keys($users->all(), 1.0);
        }

        $weightsByUserId = [];
        foreach ($users as $userId) {
            $uid = (int) $userId;
            $weightsByUserId[$uid] = (float) ($wsmTotals[$uid] ?? 0.0);
        }

        // Allocate in cents with Largest Remainder so total distributed equals allocation amount.
        $allocatedAmounts = ProportionalAllocator::allocate((float) $amount, $weightsByUserId);

        foreach ($users as $userId) {
            $userId = (int) $userId;
            $userScore = (float) ($wsmTotals[$userId] ?? 0.0);
            $sharePct = $unitTotal > 0 ? $userScore / $unitTotal : (1 / max($users->count(), 1));
            $final = (float) ($allocatedAmounts[$userId] ?? 0.0);

            $rem = Remuneration::firstOrNew([
                'user_id' => $userId,
                'assessment_period_id' => $periodId,
            ]);

            // Never overwrite published remuneration amounts
            if (!empty($rem->published_at)) {
                continue;
            }

            $rem->amount = $final;
            $rem->calculated_at = now();
            if ($actorId) {
                $rem->revised_by = $actorId;
            }
            $rem->calculation_details = [
                'method'    => 'unit_profession_wsm_proportional',
                'period_id' => $periodId,
                'generated' => now()->toDateTimeString(),
                'allocation' => [
                    'unit_id' => $alloc->unit_id,
                    'unit_name' => $alloc->unit->name ?? null,
                    'profession_id' => $professionId,
                    'published_amount' => (float) $alloc->amount,
                    'line_amount' => $amount,
                    'unit_total_wsm' => $unitTotal,
                    'user_wsm_score' => $userScore,
                    'share_percent' => round($sharePct * 100, 6),
                    'rounding' => [
                        'method' => 'largest_remainder_cents',
                        'precision' => 2,
                    ],
                ],
                'wsm' => [
                    'user_total' => $userScore,
                    'unit_total' => $unitTotal,
                    'source' => 'performance_assessments.total_wsm_score',
                ],
                // Komponen sederhana agar layar pegawai menampilkan rincian tanpa gaji dasar
                'komponen' => [
                    'dasar' => 0,
                    'pasien_ditangani' => [
                        'jumlah' => null,
                        'nilai' => $final,
                    ],
                    'review_pelanggan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                    'kontribusi_tambahan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                ],
            ];
            $rem->save();
        }
    }

    /** Mark a remuneration as published */
    public function publish(Remuneration $remuneration): RedirectResponse
    {
        $remuneration->update(['published_at' => now()]);
        return back()->with('status','Remunerasi dipublish.');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $data = $request->validate([
            'period_id'      => ['nullable','integer','exists:assessment_periods,id'],
            'unit_id'        => ['nullable','integer','exists:units,id'],
            'profession_id'  => ['nullable','integer','exists:professions,id'],
            'published'      => ['nullable','in:yes,no'],
            'payment_status' => ['nullable','in:' . implode(',', array_map(fn($e) => $e->value, RemunerationPaymentStatus::cases()))],
        ]);

        $periodId = (int) ($data['period_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);
        $professionId = (int) ($data['profession_id'] ?? 0);
        $published = $data['published'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;

        $periods  = AssessmentPeriod::orderByDesc('start_date')->get(['id','name']);
        $units = Unit::orderBy('name')->get(['id','name']);
        $professions = Profession::orderBy('name')->get(['id','name']);

        $query = Remuneration::query()
            ->with([
                'user:id,name,unit_id,profession_id',
                'assessmentPeriod:id,name',
            ])
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($published === 'yes', fn($w) => $w->whereNotNull('published_at'))
            ->when($published === 'no', fn($w) => $w->whereNull('published_at'))
            ->when($paymentStatus, fn($w) => $w->where('payment_status', $paymentStatus))
            ->when($unitId || $professionId, function ($w) use ($unitId, $professionId) {
                $w->whereHas('user', function ($u) use ($unitId, $professionId) {
                    if ($unitId) $u->where('unit_id', $unitId);
                    if ($professionId) $u->where('profession_id', $professionId);
                });
            })
            ->orderByDesc('id');

        $items = $query
            ->paginate(12)
            ->withQueryString();

        $draftCount = (clone $query)
            ->whereNull('published_at')
            ->count();

        return view('admin_rs.remunerations.index', [
            'items'    => $items,
            'periods'  => $periods,
            'units'    => $units,
            'professions' => $professions,
            'periodId' => $periodId ?: null,
            'filters'  => [
                'unit_id' => $unitId ?: null,
                'profession_id' => $professionId ?: null,
                'published' => $published,
                'payment_status' => $paymentStatus,
            ],
            'draftCount' => $draftCount,
        ]);
    }

    /** Publish all draft remunerations in the current filter scope */
    public function publishAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id'      => ['required','integer','exists:assessment_periods,id'],
            'unit_id'        => ['nullable','integer','exists:units,id'],
            'profession_id'  => ['nullable','integer','exists:professions,id'],
            'payment_status' => ['nullable','in:' . implode(',', array_map(fn($e) => $e->value, RemunerationPaymentStatus::cases()))],
        ]);

        $periodId = (int) $data['period_id'];
        $unitId = (int) ($data['unit_id'] ?? 0);
        $professionId = (int) ($data['profession_id'] ?? 0);
        $paymentStatus = $data['payment_status'] ?? null;

        $q = Remuneration::query()
            ->where('assessment_period_id', $periodId)
            ->whereNull('published_at')
            ->whereNotNull('amount')
            ->when($paymentStatus, fn($w) => $w->where('payment_status', $paymentStatus))
            ->when($unitId || $professionId, function ($w) use ($unitId, $professionId) {
                $w->whereHas('user', function ($u) use ($unitId, $professionId) {
                    if ($unitId) $u->where('unit_id', $unitId);
                    if ($professionId) $u->where('profession_id', $professionId);
                });
            });

        $count = (int) $q->count();
        if ($count === 0) {
            return back()->with('danger', 'Tidak ada remunerasi draft yang bisa dipublish.');
        }

        $q->update(['published_at' => now()]);

        return back()->with('status', "{$count} remunerasi dipublish.");
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $remuneration): View
    {
        $remuneration->load(['user:id,name,unit_id','assessmentPeriod:id,name','revisedBy:id,name']);
        $wsm = $this->buildWsmBreakdown($remuneration);
        $calcDetails = null;

        if (!empty($wsm['rows'])) {
            $calcDetails = [
                'wsm' => [
                    'criteria' => collect($wsm['rows'])->map(function ($row) {
                        return [
                            'name' => $row['criteria_name'] ?? '-',
                            'weight' => $row['weight'] ?? 0,
                            'score' => $row['score'] ?? 0,
                            'normalized' => $row['normalized'] ?? 0,
                            'contribution' => $row['contribution'] ?? 0,
                        ];
                    })->values()->all(),
                    'total' => $wsm['total'] ?? 0,
                ],
            ];
        } else {
            $calcDetails = $remuneration->calculation_details;
        }
        return view('admin_rs.remunerations.show', [
            'item' => $remuneration,
            'wsm'  => $wsm,
            'calcDetails' => $calcDetails,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Remuneration $remuneration): RedirectResponse
    {
        $data = $request->validate([
            'payment_date'   => ['nullable','date'],
            'payment_status' => ['nullable','string','max:50'],
        ]);

        if (!empty($data['payment_date'])) {
            $data['payment_status'] = RemunerationPaymentStatus::PAID->value;
        }

        $remuneration->update($data);
        return back()->with('status','Remunerasi diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}

    /**
     * Build WSM breakdown for a user's assessment in the same period as the remuneration.
     * Normalization:
     *  - benefit: score / max(score)
     *  - cost:    min(score) / score
     * Weights are taken from UnitCriteriaWeight with status ACTIVE for the user's unit and period.
     * Returns null if data not available.
     */
    private function buildWsmBreakdown(Remuneration $rem): ?array
    {
        $details = $rem->calculation_details ?? [];
        if (!empty($details['wsm']['criteria_rows'])) {
            $rows = [];
            foreach ($details['wsm']['criteria_rows'] as $row) {
                $rows[] = [
                    'criteria_name' => $row['label'] ?? ($row['key'] ?? '-'),
                    'type'          => $row['type'] ?? '-',
                    'weight'        => (float) ($row['weight'] ?? 0),
                    'score'         => (float) ($row['raw'] ?? 0),
                    'min'           => 0,
                    'max'           => (float) ($row['criteria_total'] ?? 0),
                    'normalized'    => round((float) ($row['normalized'] ?? 0), 4),
                    'contribution'  => round((float) ($row['weighted'] ?? 0), 4),
                ];
            }

            return [
                'rows'  => $rows,
                'total' => round((float) ($details['wsm']['user_total'] ?? $details['wsm']['total'] ?? 0), 4),
            ];
        }

        $userId = $rem->user_id;
        $periodId = $rem->assessment_period_id;
        $unitId = $rem->user->unit_id ?? null;
        if (!$unitId) return null;

        $assessment = PerformanceAssessment::with(['details.performanceCriteria:id,name,type'])
            ->where('user_id', $userId)
            ->where('assessment_period_id', $periodId)
            ->first();
        if (!$assessment) return null;

        $detailByCrit = [];
        $criteriaIds = [];
        foreach ($assessment->details as $d) {
            $cid = $d->performance_criteria_id;
            $criteriaIds[] = $cid;
            $detailByCrit[$cid] = [
                'score'    => (float) $d->score,
                'criteria' => $d->performanceCriteria,
            ];
        }
        if (!$criteriaIds) return null;

        // Fetch ACTIVE weights for this unit & period
        $weights = UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('assessment_period_id', $periodId)
            ->where('status', 'active')
            ->get(['performance_criteria_id','weight'])
            ->keyBy('performance_criteria_id');

        // Compute min/max per criteria in this period for normalization
        $minMax = PerformanceAssessmentDetail::query()
            ->selectRaw('performance_criteria_id, MIN(score) as min_score, MAX(score) as max_score')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereHas('performanceAssessment', function ($q) use ($periodId) {
                $q->where('assessment_period_id', $periodId);
            })
            ->groupBy('performance_criteria_id')
            ->get()
            ->keyBy('performance_criteria_id');

        $rows = [];
        $total = 0.0;
        foreach ($criteriaIds as $cid) {
            $crit = $detailByCrit[$cid]['criteria'];
            $score = $detailByCrit[$cid]['score'];
            $w    = (float) ($weights[$cid]->weight ?? 0); // percent
            $mm   = $minMax[$cid] ?? null;
            $min  = $mm ? (float)$mm->min_score : 0.0;
            $max  = $mm ? (float)$mm->max_score : 0.0;
            $norm = 0.0;
            if ($crit && $crit->type === PerformanceCriteriaType::BENEFIT) {
                $norm = $max > 0 ? ($score / $max) : 0.0;
            } else {
                $norm = ($score > 0 && $min > 0) ? ($min / $score) : 0.0;
            }
            if ($norm < 0) $norm = 0.0; if ($norm > 1) $norm = 1.0;
            $contrib = $norm * ($w / 100.0);
            $total += $contrib;
            $rows[] = [
                'criteria_name' => $crit?->name ?? ('Kriteria #'.$cid),
                'type'          => $crit?->type?->value ?? '-',
                'weight'        => $w,
                'score'         => $score,
                'min'           => $min,
                'max'           => $max,
                'normalized'    => round($norm, 4),
                'contribution'  => round($contrib, 4),
            ];
        }

        return [
            'rows'  => $rows,
            'total' => round($total, 4),
        ];
    }
}
