<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Remuneration;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Services\AssessmentApprovals\AssessmentApprovalDetailService;
use App\Support\ProportionalAllocator;
use App\Enums\AssessmentValidationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    /**
     * Normalize calculation_details schema so the medical staff UI can render
     * both legacy/demo payloads and the newer WSM-proportional payload.
     *
     * @param array<mixed> $calc
     * @return array<string,mixed>
     */
    private function normalizeCalcDetails(array $calc, Remuneration $remuneration): array
    {
        // Legacy/demo seeder payload (demo_equal_split)
        if (!empty($calc) && (isset($calc['policy']) || isset($calc['allocation_amount'])) && !isset($calc['allocation'])) {
            $perUser = (float) ($calc['per_user_amount'] ?? ($remuneration->amount ?? 0));
            $allocAmount = $calc['allocation_amount'] ?? null;

            $calc = [
                'method' => (string) ($calc['policy'] ?? 'legacy'),
                'period_id' => (int) ($remuneration->assessment_period_id ?? 0),
                'allocation' => [
                    'unit_id' => (int) ($calc['unit_id'] ?? ($remuneration->user?->unit_id ?? 0)),
                    'profession_id' => (int) ($calc['profession_id'] ?? ($remuneration->user?->profession_id ?? 0)),
                    'published_amount' => $allocAmount,
                    'line_amount' => $allocAmount,
                ],
                'komponen' => [
                    'absensi' => ['jumlah' => null, 'nilai' => 0],
                    'kedisiplinan' => ['jumlah' => null, 'nilai' => 0],
                    'kontribusi_tambahan' => ['jumlah' => 0, 'nilai' => 0],
                    // The UI needs a single nominal; legacy demo didn't split components.
                    'pasien_ditangani' => ['jumlah' => null, 'nilai' => $perUser],
                    'review_pelanggan' => ['jumlah' => 0, 'nilai' => 0],
                ],
            ];
        }

        // Ensure komponen keys exist to avoid lots of UI fallbacks.
        $amount = (float) ($remuneration->amount ?? 0);
        $komponen = (array) ($calc['komponen'] ?? []);
        $komponen += [
            'absensi' => ['jumlah' => null, 'nilai' => 0],
            'kedisiplinan' => ['jumlah' => null, 'nilai' => 0],
            'kontribusi_tambahan' => ['jumlah' => 0, 'nilai' => 0],
            'pasien_ditangani' => ['jumlah' => null, 'nilai' => $amount],
            'review_pelanggan' => ['jumlah' => 0, 'nilai' => 0],
        ];
        $calc['komponen'] = $komponen;

        // Backfill allocation + WSM info from DB when missing.
        $periodId = (int) ($remuneration->assessment_period_id ?? 0);
        $unitId = (int) ($remuneration->user?->unit_id ?? data_get($calc, 'allocation.unit_id', 0));
        $professionId = (int) ($remuneration->user?->profession_id ?? data_get($calc, 'allocation.profession_id', 0));

        if ($periodId > 0 && $unitId > 0 && empty($calc['allocation'])) {
            $alloc = Allocation::query()
                ->where('assessment_period_id', $periodId)
                ->where('unit_id', $unitId)
                ->where(function ($q) use ($professionId) {
                    // Prefer exact profession allocation; fall back to "all professions" allocation.
                    $q->where('profession_id', $professionId)->orWhereNull('profession_id');
                })
                ->orderByRaw('profession_id is null')
                ->first();

            if ($alloc) {
                $calc['allocation'] = [
                    'unit_id' => (int) $alloc->unit_id,
                    'unit_name' => $alloc->unit?->name,
                    'profession_id' => $alloc->profession_id !== null ? (int) $alloc->profession_id : null,
                    'published_amount' => (float) $alloc->amount,
                    'line_amount' => (float) $alloc->amount,
                ];
            }
        }

        $allocUnitId = (int) data_get($calc, 'allocation.unit_id', 0);
        $allocProfessionId = data_get($calc, 'allocation.profession_id');
        if ($periodId > 0 && $allocUnitId > 0 && (empty($calc['wsm']) || !isset($calc['allocation']['unit_total_wsm']))) {
            $userId = (int) $remuneration->user_id;

            $userWsm = (float) (DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->where('user_id', $userId)
                ->value('total_wsm_score') ?? 0);

            $groupWsmQuery = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->where('pa.assessment_period_id', $periodId)
                ->where('u.unit_id', $allocUnitId);

            if ($allocProfessionId !== null) {
                $groupWsmQuery->where('u.profession_id', (int) $allocProfessionId);
            }

            $groupTotalWsm = (float) ($groupWsmQuery->sum('pa.total_wsm_score') ?? 0);
            if ($groupTotalWsm <= 0) {
                // Fallback: equal weights if no WSM.
                $countQuery = DB::table('users')->where('unit_id', $allocUnitId);
                if ($allocProfessionId !== null) {
                    $countQuery->where('profession_id', (int) $allocProfessionId);
                }
                $groupCount = (int) ($countQuery->count() ?? 0);
                $groupTotalWsm = max($groupCount, 1);
                $userWsm = 1.0;
            }

            $sharePct = $groupTotalWsm > 0 ? ($userWsm / $groupTotalWsm) * 100.0 : null;

            $calc['wsm'] = $calc['wsm'] ?? [];
            $calc['wsm'] = array_merge((array) $calc['wsm'], [
                'user_total' => round($userWsm, 2),
                'unit_total' => round($groupTotalWsm, 2),
                'source' => 'performance_assessments.total_wsm_score',
            ]);

            if (!empty($calc['allocation']) && is_array($calc['allocation'])) {
                $calc['allocation']['unit_total_wsm'] = round($groupTotalWsm, 2);
                $calc['allocation']['user_wsm_score'] = round($userWsm, 2);
                $calc['allocation']['share_percent'] = $sharePct !== null ? round($sharePct, 6) : null;
            }
        }

        return $calc;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $userId = (int) Auth::id();

        // Always allow user to see historical remunerations, but never show data for ACTIVE period (shouldn't exist by rule)
        $items = Remuneration::with('assessmentPeriod')
            ->where('user_id', $userId)
            ->whereHas('assessmentPeriod', function ($q) {
                $q->where('status', '!=', AssessmentPeriod::STATUS_ACTIVE);
            })
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $activePeriod = AssessmentPeriod::active()->first(['id','name','status']);
        $statusCard = null;
        $banners = [];

        // If there is an ACTIVE period, show only informative running-period message (no approval progress)
        if ($activePeriod) {
            $statusCard = [
                'mode' => 'active',
                'period_id' => $activePeriod->id,
                'period_name' => $activePeriod->name,
                'message' => 'Periode sedang berjalan. Penilaian masih dalam proses. Remunerasi akan tersedia setelah periode ditutup, penilaian tervalidasi, dan alokasi remunerasi unit dipublish.',
            ];

            return view('pegawai_medis.remunerations.index', [
                'items' => $items,
                'statusCard' => $statusCard,
                'banners' => $banners,
            ]);
        }

        // Find the latest locked/approval period assessment for this user (used for process status & prerequisite banners)
        $pendingAssessment = PerformanceAssessment::query()
            ->with('assessmentPeriod:id,name,status')
            ->where('user_id', $userId)
            ->whereHas('assessmentPeriod', function ($q) {
                $q->whereIn('status', [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED]);
            })
            ->orderByDesc('assessment_period_id')
            ->orderByDesc('id')
            ->first();

        if ($pendingAssessment) {
            $period = $pendingAssessment->assessmentPeriod;
            $periodId = (int) $pendingAssessment->assessment_period_id;

            $remExists = Remuneration::query()
                ->where('user_id', $userId)
                ->where('assessment_period_id', $periodId)
                ->exists();

            if (!$remExists) {
                // Approval progress details (only shown for non-ACTIVE periods)
                $levels = DB::table('assessment_approvals')
                    ->where('performance_assessment_id', $pendingAssessment->id)
                    ->select('level', 'status', 'acted_at')
                    ->orderBy('level')
                    ->get();

                $current = 'pending';
                $highestApproved = 0;
                foreach ($levels as $lv) {
                    if ($lv->status === 'approved') {
                        $highestApproved = max($highestApproved, (int) $lv->level);
                        $current = 'approved';
                    } elseif ($lv->status === 'pending') {
                        $current = 'pending';
                        break;
                    } elseif ($lv->status === 'rejected') {
                        $current = 'rejected';
                        break;
                    }
                }

                $statusCard = [
                    'mode' => 'process',
                    'assessment_id' => $pendingAssessment->id,
                    'period_id' => $periodId,
                    'period_name' => $period?->name,
                    'highestApproved' => $highestApproved,
                    'currentStatus' => $current,
                    'levels' => $levels,
                    'validation_status' => (string) ($pendingAssessment->validation_status ?? ''),
                ];

                // Banners (prerequisites)
                if ((string) ($pendingAssessment->validation_status ?? '') !== AssessmentValidationStatus::VALIDATED->value) {
                    $banners[] = [
                        'type' => 'warning',
                        'message' => 'Penilaian Anda belum tervalidasi final. Remunerasi akan tersedia setelah proses validasi selesai.',
                    ];
                } else {
                    $unitId = (int) ($user?->unit_id ?? 0);
                    $professionId = (int) ($user?->profession_id ?? 0);

                    $allocation = null;
                    if ($unitId) {
                        if ($professionId) {
                            $allocation = Allocation::query()
                                ->where('assessment_period_id', $periodId)
                                ->where('unit_id', $unitId)
                                ->where('profession_id', $professionId)
                                ->first();
                        }
                        if (!$allocation) {
                            $allocation = Allocation::query()
                                ->where('assessment_period_id', $periodId)
                                ->where('unit_id', $unitId)
                                ->whereNull('profession_id')
                                ->first();
                        }
                    }

                    if (!$allocation) {
                        $banners[] = [
                            'type' => 'warning',
                            'message' => 'Alokasi remunerasi unit Anda untuk periode ini belum tersedia. Silakan menunggu Admin RS membuat alokasi.',
                        ];
                    } elseif (empty($allocation->published_at)) {
                        $banners[] = [
                            'type' => 'warning',
                            'message' => 'Penilaian telah tervalidasi. Saat ini menunggu Admin RS mempublish alokasi remunerasi unit Anda.',
                        ];
                    } else {
                        $banners[] = [
                            'type' => 'info',
                            'message' => 'Penilaian telah tervalidasi dan alokasi unit sudah dipublish. Remunerasi akan muncul setelah Admin RS menjalankan perhitungan dan mempublish remunerasi.',
                        ];
                    }
                }
            } else {
                // Remuneration exists (draft/published) for this period; no progress card needed.
                $rem = Remuneration::query()
                    ->where('user_id', $userId)
                    ->where('assessment_period_id', $periodId)
                    ->first();
                if ($rem && empty($rem->published_at)) {
                    $banners[] = [
                        'type' => 'info',
                        'message' => 'Remunerasi Anda telah dihitung dan sedang menunggu publikasi.',
                    ];
                }
            }
        }

        return view('pegawai_medis.remunerations.index', [
            'items' => $items,
            'statusCard' => $statusCard,
            'banners' => $banners,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $id): View
    {
        $remuneration = $id; // implicit model binding
        abort_unless($remuneration->user_id === Auth::id(), 403);
        $remuneration->load(['assessmentPeriod','user.unit','user.profession']);

        $period = $remuneration->assessmentPeriod;
        $userId = Auth::id();

        // Jumlah review pada rentang tanggal periode
        $reviewCount = 0;
        if ($period && $period->start_date && $period->end_date) {
            $reviewCount = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->where('rd.medical_staff_id', $userId)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->whereBetween('r.created_at', [
                    $period->start_date . ' 00:00:00',
                    $period->end_date   . ' 23:59:59',
                ])
                ->count();
        }

        $calc = $this->normalizeCalcDetails(($remuneration->calculation_details ?? []), $remuneration);
        $patientsHandled = data_get($calc, 'komponen.pasien_ditangani.jumlah');

        $allocationAmount = data_get($calc, 'allocation.published_amount');
        if ($allocationAmount === null) {
            $allocationAmount = data_get($calc, 'allocation.line_amount');
        }
        if ($allocationAmount === null) {
            // Legacy/demo key
            $allocationAmount = data_get($calc, 'allocation_amount');
        }

        $unitName = optional($remuneration->user?->unit)->name
            ?? data_get($calc, 'allocation.unit_name');
        $professionName = optional($remuneration->user?->profession)->name
            ?? data_get($calc, 'allocation.profession_name')
            ?? data_get($calc, 'allocation.profession');

        $allocationLabel = $unitName && $professionName
            ? 'Alokasi untuk ' . $professionName . ' di ' . $unitName
            : 'Alokasi profesi-unit';

        $quantities = [
            ['label' => 'Kehadiran (Absensi) (hari)', 'value' => data_get($calc, 'komponen.absensi.jumlah'), 'icon' => 'fa-calendar-check'],
            ['label' => 'Kedisiplinan 360', 'value' => data_get($calc, 'komponen.kedisiplinan.jumlah'), 'icon' => 'fa-clipboard-check'],
            ['label' => 'Pasien Ditangani', 'value' => $patientsHandled ?? data_get($calc, 'komponen.pasien_ditangani.jumlah'), 'icon' => 'fa-user-injured'],
            ['label' => 'Jumlah Review', 'value' => $reviewCount ?: data_get($calc, 'komponen.review_pelanggan.jumlah'), 'icon' => 'fa-star'],
        ];

        // Per-kriteria nominal breakdown: split amount proportionally by each criteria's WSM contribution.
        $criteriaAllocations = [];
        $amount = $remuneration->amount !== null ? (float) $remuneration->amount : null;
        if ($amount !== null && $amount > 0 && $period) {
            $pa = PerformanceAssessment::query()
                ->with(['assessmentPeriod', 'user'])
                ->where('assessment_period_id', (int) $period->id)
                ->where('user_id', (int) $remuneration->user_id)
                ->first();

            if ($pa) {
                $breakdown = app(AssessmentApprovalDetailService::class)->getBreakdown($pa);
                $totalWsm = (float) ($breakdown['total'] ?? 0.0);

                if (!empty($breakdown['hasWeights']) && $totalWsm > 0 && !empty($breakdown['rows'])) {
                    $weights = [];
                    $meta = [];
                    foreach ($breakdown['rows'] as $r) {
                        $cid = (int) ($r['criteria_id'] ?? 0);
                        if ($cid <= 0) continue;
                        $w = (float) ($r['contribution'] ?? 0.0);
                        if ($w < 0) $w = 0.0;
                        $weights[$cid] = $w;
                        $meta[$cid] = [
                            'criteria_id' => $cid,
                            'criteria_name' => (string) ($r['criteria_name'] ?? '-'),
                            'weight' => (float) ($r['weight'] ?? 0.0),
                            'score_wsm' => (float) ($r['score_wsm'] ?? 0.0),
                            'contribution' => $w,
                        ];
                    }

                    if (!empty($weights) && array_sum($weights) > 0) {
                        $allocated = ProportionalAllocator::allocate((float) $amount, $weights);

                        foreach ($allocated as $cid => $nominal) {
                            $cid = (int) $cid;
                            $m = $meta[$cid] ?? null;
                            if (!$m) continue;
                            $criteriaAllocations[] = $m + ['nominal' => (float) $nominal];
                        }

                        usort($criteriaAllocations, fn($a, $b) => ((float)($b['nominal'] ?? 0)) <=> ((float)($a['nominal'] ?? 0)));
                    }
                }
            }
        }

        return view('pegawai_medis.remunerations.show', [
            'item' => $remuneration,
            'reviewCount' => $reviewCount,
            'patientsHandled' => $patientsHandled,
            'calc' => $calc,
            'allocationAmount' => $allocationAmount,
            'allocationLabel' => $allocationLabel,
            'unitName' => $unitName,
            'professionName' => $professionName,
            'quantities' => $quantities,
            'criteriaAllocations' => $criteriaAllocations,
        ]);
    }
}
