<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\Remuneration;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Enums\AssessmentValidationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RemunerationController extends Controller
{
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

        $calc = $remuneration->calculation_details ?? [];
        $patientsHandled = data_get($calc, 'komponen.pasien_ditangani.jumlah');
        $allocationAmount = data_get($calc, 'allocation')
            ?? data_get($calc, 'allocation.published_amount')
            ?? data_get($calc, 'allocation.line_amount');

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
        ]);
    }
}
