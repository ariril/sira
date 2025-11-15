<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

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
                ->where('role', 'pegawai_medis')->count(),
            'total_admin'   => User::whereIn('unit_id', $scopeUnitIds)
                ->where('role', 'admin_rs')->count(),
        ];

        $review = [
            'avg_rating_unit_30d'   => null,
            'total_ulasan_unit_30d' => 0,
            'top_staff'             => collect(), // top professions across scope units
            'recent_comments'       => collect(),
        ];

        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('review_details') && Schema::hasTable('users')) {
            $from = Carbon::now()->subDays(30)->toDateTimeString();

            // Gunakan user.unit_id untuk scope unit, karena struktur review tidak memiliki unit_id
            $base = DB::table('review_details as rd')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->whereIn('u.unit_id', $scopeUnitIds)
                ->where('rd.created_at', '>=', $from);

            $review['avg_rating_unit_30d']   = (clone $base)->avg('rd.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->count();

            // Top staf (berdasar user), sertakan nama profesi jika tabel professions tersedia
            $top = DB::table('review_details as rd')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.id', DB::raw('u.name as nama'), DB::raw('COALESCE(p.name, NULL) as jabatan'),
                    DB::raw('AVG(rd.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->whereIn('u.unit_id', $scopeUnitIds)
                ->groupBy('u.id', 'u.name', 'p.name')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByDesc('avg_rating')
                ->limit(5)->get();
            $review['top_staff'] = $top;

            $recent = DB::table('review_details as rd')
                ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->select('u.name as nama', 'rd.rating', 'rd.comment as komentar', 'rd.created_at')
                ->whereIn('u.unit_id', $scopeUnitIds)
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
            $kinerja['periode_aktif'] = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status = 'active'"))
                ->orderByDesc('id')
                ->first();
        }

        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('performance_assessments') && Schema::hasTable('users')) {
            $kinerja['penilaian_pending'] = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->whereIn('u.unit_id', $scopeUnitIds)
                ->where('pa.validation_status', 'Menunggu Validasi')
                ->count();
        }

        // Ringkas ke struktur yang dipakai view kepala_poli.dashboard
        $summary = [
            'wsm'            => 0, // placeholder, belum ada perhitungan WSM khusus poliklinik
            'reviews_30d'    => $review['total_ulasan_unit_30d'] ?? 0,
            'avg_rating'     => $review['avg_rating_unit_30d'] ?? 0,
            'pending_assess' => $kinerja['penilaian_pending'] ?? 0,
        ];

        return view('kepala_poli.dashboard', [
            'stats'         => $summary,
            'approvalsList' => null,
        ]);
    }
}
