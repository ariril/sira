<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\ReviewStatus;
use App\Models\AssessmentPeriod;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $me = Auth::user();
        $scopeUnitIds = collect();

        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                // ambil semua unit anak di bawah unit Kepala Poliklinik
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                // fallback: semua unit bertipe poliklinik
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        // Stats agregat untuk unit lingkup poliklinik
        $stats = [
            'total_pegawai' => User::whereIn('unit_id', $scopeUnitIds)->count(),
            'total_dokter'  => User::whereIn('unit_id', $scopeUnitIds)
                ->whereNotNull('profession_id')
                ->role('pegawai_medis')->count(),
            'total_admin'   => User::whereIn('unit_id', $scopeUnitIds)
                ->role('admin_rs')->count(),
        ];

        $review = [
            'avg_rating_unit_30d'   => null,
            'total_ulasan_unit_30d' => 0,
            'top_staff'             => collect(), // top professions across scope units
            'recent_comments'       => collect(),
        ];

        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('review_details') && Schema::hasTable('reviews') && Schema::hasTable('users')) {
            $from = Carbon::now()->subDays(30)->toDateTimeString();

            $base = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->where('r.created_at', '>=', $from);

            $review['avg_rating_unit_30d']   = (clone $base)->whereNotNull('rd.rating')->avg('rd.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->whereNotNull('rd.rating')->count('rd.rating');

            $top = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.id', DB::raw('u.name as nama'), DB::raw('COALESCE(p.name, NULL) as jabatan'),
                    DB::raw('AVG(rd.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->whereNotNull('rd.rating')
                ->groupBy('u.id', 'u.name', 'p.name')
                ->havingRaw('COUNT(rd.rating) >= 5')
                ->orderByDesc('avg_rating')
                ->limit(5)->get();
            $review['top_staff'] = $top;

            $recent = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.name as nama', 'rd.rating', 'rd.comment as komentar', 'rd.created_at')
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->whereNotNull('rd.comment')
                ->orderByDesc('rd.created_at')
                ->limit(10)->get();
            $review['recent_comments'] = $recent;
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

        if (
            $scopeUnitIds->isNotEmpty() &&
            Schema::hasTable('assessment_approvals') &&
            Schema::hasTable('performance_assessments') &&
            Schema::hasTable('users')
        ) {
            $kinerja['penilaian_pending'] = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->where('aa.level', 3)
                ->where('aa.status', 'pending')
                ->whereIn('u.unit_id', $scopeUnitIds)
                ->count();
        }

        // Ringkas ke struktur yang dipakai view kepala_poli.dashboard
        $summary = [
            'wsm'            => null, // belum ada perhitungan WSM khusus poliklinik
            'reviews_30d'    => $review['total_ulasan_unit_30d'] ?? 0,
            'avg_rating'     => $review['avg_rating_unit_30d'] ?? null,
            'pending_assess' => $kinerja['penilaian_pending'] ?? 0,
        ];

        $notifications = [];
        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('unit_criteria_weights')) {
            $pendingUnitCount = DB::table('unit_criteria_weights')
                ->whereIn('unit_id', $scopeUnitIds)
                ->where('status', 'pending')
                ->distinct('unit_id')
                ->count('unit_id');
            if ($pendingUnitCount > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'text' => $pendingUnitCount . ' unit menunggu persetujuan bobot.',
                    'href' => route('kepala_poliklinik.unit_criteria_weights.index', [], false) . '?status=pending',
                ];
            }
        }

        if (($summary['pending_assess'] ?? 0) > 0) {
            $notifications[] = [
                'type' => 'info',
                'text' => $summary['pending_assess'] . ' penilaian menunggu persetujuan Level 3.',
                'href' => route('kepala_poliklinik.assessments.pending', [], false) . '?status=pending_l3',
            ];
        }

        if (!$kinerja['periode_aktif']) {
            $notifications[] = [
                'type' => 'error',
                'text' => 'Tidak ada periode aktif. Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.',
                'href' => null,
            ];
        }

        return view('kepala_poli.dashboard', [
            'stats'         => $summary,
            'notifications' => $notifications,
            'activePeriod'  => $kinerja['periode_aktif'],
        ]);
    }
}
