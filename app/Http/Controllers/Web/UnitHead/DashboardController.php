<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $unitId = optional(Auth::user())->unit_id;

        $stats = [
            'members' => 0,
            'pending' => 0,
            'avg_wsm' => '—',
            'add_tasks' => 0,
        ];

        if ($unitId) {
            $stats['members'] = (int) User::query()
                ->where('unit_id', $unitId)
                ->role('pegawai_medis')
                ->count();
        }

        $review = [
            'avg_rating_unit_30d'   => null,
            'total_ulasan_unit_30d' => 0,
            'top_staff'             => collect(),   // diisi "top professions" agar UI tetap dapat data
            'recent_comments'       => collect(),
        ];

        if ($unitId && Schema::hasTable('review_details') && Schema::hasTable('reviews') && Schema::hasTable('users')) {
            $from = Carbon::now()->subDays(30)->toDateTimeString();

            $base = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->where('r.unit_id', $unitId)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->where('r.created_at', '>=', $from);

            $review['avg_rating_unit_30d']   = (clone $base)->whereNotNull('rd.rating')->avg('rd.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->whereNotNull('rd.rating')->count('rd.rating');

            // "top_staff" → top staff within unit (min 3 review), include profession if available
            $review['top_staff'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.id', DB::raw('u.name as nama'), DB::raw('COALESCE(p.name, NULL) as jabatan'),
                    DB::raw('AVG(rd.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->where('r.unit_id', $unitId)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->whereNotNull('rd.rating')
                ->groupBy('u.id', 'u.name', 'p.name')
                ->havingRaw('COUNT(rd.rating) >= 3')
                ->orderByDesc('avg_rating')
                ->limit(5)->get();

            // Komentar terbaru
            $review['recent_comments'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.name as nama', 'rd.rating', 'rd.comment as komentar', 'rd.created_at')
                ->where('r.unit_id', $unitId)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->whereNotNull('rd.comment')
                ->orderByDesc('rd.created_at')
                ->limit(10)->get();
        }

        $kinerja = [
            'penilaian_pending' => 0,
            'periode_aktif'     => null,
        ];

        if (Schema::hasTable('assessment_periods')) {
            // Ambil hanya periode berstatus active; jika tidak ada, set null agar banner muncul
            $kinerja['periode_aktif'] = DB::table('assessment_periods')
                ->where('status', AssessmentPeriod::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();
        }

        // Avg WSM (periode aktif) untuk tampilan dashboard.
        if (
            $unitId &&
            !empty($kinerja['periode_aktif']?->id) &&
            Schema::hasTable('performance_assessments') &&
            Schema::hasTable('users')
        ) {
            $avg = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->where('u.unit_id', $unitId)
                ->where('pa.assessment_period_id', (int) $kinerja['periode_aktif']->id)
                ->avg('pa.total_wsm_score');

            if ($avg !== null) {
                $stats['avg_wsm'] = number_format((float) $avg, 2);
            }
        }

        $notifications = [];
        if ($unitId && Schema::hasTable('unit_criteria_weights')) {
            $rejectedCount = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('status', 'rejected')
                ->count();
            if ($rejectedCount > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'text' => $rejectedCount . ' bobot ditolak. Revisi kemudian ajukan kembali.',
                    'href' => route('kepala_unit.unit-criteria-weights.index'),
                ];
            }

            $activePeriod = $kinerja['periode_aktif'];
            if ($activePeriod) {
                $periodId = $activePeriod->id;
                $committed = (float) DB::table('unit_criteria_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('status', ['active','pending'])
                    ->sum('weight');
                if ($committed < 100) {
                    $notifications[] = [
                        'type' => 'warning',
                        'text' => 'Bobot periode ' . ($activePeriod->name ?? '') . ' baru mencapai ' . number_format($committed,2) . '%. Lengkapi hingga 100%.',
                        'href' => route('kepala_unit.unit-criteria-weights.index', ['period_id' => $periodId]),
                    ];
                }

                $draftTotal = (float) DB::table('unit_criteria_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('status', ['draft','rejected'])
                    ->sum('weight');
                if ($draftTotal > 0) {
                    $draftTextPrefix = $rejectedCount > 0 ? 'Pengajuan bobot ditolak. ' : '';
                    $draftTextSuffix = $rejectedCount > 0 ? ' Periksa komentar, revisi bobot, lalu ajukan ulang.' : '';
                    $notifications[] = [
                        'type' => 'info',
                        'text' => $draftTextPrefix . 'Masih ada ' . number_format($draftTotal,2) . '% bobot draft/revisi yang belum diajukan.' . $draftTextSuffix,
                        'href' => route('kepala_unit.unit-criteria-weights.index', ['period_id' => $periodId]),
                    ];
                }

                // Success state: weights complete and already active, or complete but still pending approval.
                if ($rejectedCount === 0 && $draftTotal <= 0 && $committed >= 100) {
                    $breakdown = DB::table('unit_criteria_weights')
                        ->where('unit_id', $unitId)
                        ->where('assessment_period_id', $periodId)
                        ->selectRaw("SUM(CASE WHEN status='active' THEN weight ELSE 0 END) as active_weight")
                        ->selectRaw("SUM(CASE WHEN status='pending' THEN weight ELSE 0 END) as pending_weight")
                        ->first();

                    $activeWeight = (float) ($breakdown->active_weight ?? 0);
                    $pendingWeight = (float) ($breakdown->pending_weight ?? 0);

                    if ($activeWeight >= 100 && $pendingWeight <= 0) {
                        $notifications[] = [
                            'type' => 'success',
                            'text' => 'Bobot kriteria periode ' . ($activePeriod->name ?? '') . ' sudah disetujui dan aktif.',
                            'href' => route('kepala_unit.unit-criteria-weights.index', ['period_id' => $periodId]),
                        ];
                    } elseif ($pendingWeight > 0) {
                        $notifications[] = [
                            'type' => 'info',
                            'text' => 'Bobot kriteria periode ' . ($activePeriod->name ?? '') . ' sudah lengkap 100% dan menunggu persetujuan.',
                            'href' => route('kepala_unit.unit-criteria-weights.index', ['period_id' => $periodId]),
                        ];
                    }
                }
            }
        }

        if ($unitId && Schema::hasTable('unit_rater_weights')) {
            $activePeriodId = (int) ($kinerja['periode_aktif']?->id ?? 0);

            $rejectedRaterWeights = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->when($activePeriodId > 0, fn($q) => $q->where('assessment_period_id', $activePeriodId))
                ->where('status', 'rejected')
                ->count();

            if ($rejectedRaterWeights > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'text' => $rejectedRaterWeights . ' bobot penilai 360 ditolak. Revisi kemudian ajukan kembali.',
                    'href' => route('kepala_unit.rater_weights.index', [
                        'status' => 'rejected',
                    ]),
                ];
            }
        }

        if ($unitId && Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments') && Schema::hasTable('users')) {
            $pendingApprovals = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->where('aa.level', 2)
                ->where('aa.status', 'pending')
                ->where('u.unit_id', $unitId)
                ->count();

            $stats['pending'] = (int) $pendingApprovals;

            if ($pendingApprovals > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'text' => $pendingApprovals . ' penilaian menunggu persetujuan Level 2.',
                    'href' => route('kepala_unit.assessments.pending', ['status' => 'pending_l2']),
                ];
            }
        }

        if ($unitId && Schema::hasTable('reviews')) {
            $pendingReviews = DB::table('reviews')
                ->where('unit_id', $unitId)
                ->where('status', ReviewStatus::PENDING->value)
                ->count();
            if ($pendingReviews > 0) {
                $notifications[] = [
                    'type' => 'info',
                    'text' => $pendingReviews . ' ulasan pasien menunggu approval.',
                    'href' => route('kepala_unit.reviews.index', ['status' => ReviewStatus::PENDING->value]),
                ];
            }
        }

        if ($unitId && Schema::hasTable('additional_task_claims') && Schema::hasTable('additional_tasks')) {
            $countsByStatus = DB::table('additional_task_claims as c')
                ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                ->where('t.unit_id', $unitId)
                ->whereIn('c.status', AdditionalTaskStatusService::REVIEW_WAITING_STATUSES)
                ->groupBy('c.status')
                ->select('c.status', DB::raw('count(*) as aggregate'))
                ->pluck('aggregate', 'status');

            $submittedCount = (int) ($countsByStatus['submitted'] ?? 0);
            $pendingTaskClaims = $submittedCount;

            $stats['add_tasks'] = (int) $pendingTaskClaims;

            if ($pendingTaskClaims > 0) {
                $notifications[] = [
                    'type' => 'info',
                    'text' => $pendingTaskClaims . ' klaim tugas tambahan butuh tindakan (' . $submittedCount . ' menunggu review).',
                    'href' => route('kepala_unit.additional_task_claims.review_index'),
                ];
            }
        }

        if (!$kinerja['periode_aktif']) {
            $notifications[] = [
                'type' => 'error',
                'text' => 'Tidak ada periode aktif. Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.',
                'href' => null,
            ];
        }

        return view('kepala_unit.dashboard', [
            'stats' => $stats,
            'review' => $review,
            'kinerja' => $kinerja,
            'notifications' => $notifications,
            'activePeriod' => $kinerja['periode_aktif'],
        ]);
    }
}
