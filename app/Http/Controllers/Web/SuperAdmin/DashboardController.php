<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // ====== USER STATS ======
        $stats = [
            'total_user'        => User::count(),
            'total_unit'        => Unit::count(),
            'total_profesi'     => Profession::count(),
            'pegawai_medis'     => User::where('role','pegawai_medis')->count(),
            'kepala_unit'       => User::where('role','kepala_unit')->count(),
            'kepala_poliklinik' => User::where('role','kepala_poliklinik')->count(), // role baru
            'administrasi'      => User::where('role','administrasi')->count(),
            'super_admin'       => User::where('role','super_admin')->count(),
            'unverified'        => User::whereNull('email_verified_at')->count(),
        ];

        // ====== REVIEW (berbasis reviews + review_details sesuai ERD) ======
        $from = now()->subDays(30);

        // Rata-rata rating 30 hari (ambil dari review_details.created_at)
        $avgRating30d = DB::table('review_details')
            ->where('created_at', '>=', $from)
            ->avg('rating');

        $totalReview30d = DB::table('review_details')
            ->where('created_at', '>=', $from)
            ->count();

        // Rata-rata rating per unit (join reviews.unit_id)
        $ratingPerUnit = DB::table('review_details as rd')
            ->join('reviews as r', 'r.id', '=', 'rd.review_id')
            ->leftJoin('units as u', 'u.id', '=', 'r.unit_id')
            ->selectRaw('COALESCE(u.name, "Tidak diketahui") as nama_unit, AVG(rd.rating) as avg_rating, COUNT(*) as total')
            ->where('rd.created_at', '>=', $from)
            ->groupBy('u.name')
            ->orderByDesc('avg_rating')
            ->get();

        // Top "tenaga medis" untuk UI lama:
        // Karena review tak lagi per-user, kita pakai WSM (performance_assessments)
        // -> tampilkan top 5 user (pegawai_medis) dengan rata-rata total_wsm_score tertinggi
        $topTenagaMedis = DB::table('performance_assessments as pa')
            ->join('users as u', 'u.id', '=', 'pa.user_id')
            ->selectRaw('u.id, u.name as nama, u.position as jabatan, AVG(pa.total_wsm_score) as avg_rating, COUNT(*) as total_ulasan')
            ->where('u.role', 'pegawai_medis')
            ->groupBy('u.id', 'u.name', 'u.position')
            ->havingRaw('COUNT(*) >= 1')
            ->orderByDesc('avg_rating')
            ->limit(5)
            ->get();

        $review = [
            'avg_rating_30d'   => $avgRating30d,
            'total_30d'        => $totalReview30d,
            'top_tenaga_medis' => $topTenagaMedis, // shape kolom disesuaikan agar UI lama tetap jalan (nama, jabatan, avg_rating, total_ulasan)
            'rating_per_unit'  => $ratingPerUnit,  // (nama_unit, avg_rating, total)
        ];

        // ====== KINERJA & REMUNERASI ======
        $activePeriod = AssessmentPeriod::where('is_active', 1)->orderByDesc('id')->first();

        $totalRemunPeriode = null;
        if ($activePeriod) {
            $totalRemunPeriode = DB::table('remunerations')
                ->where('assessment_period_id', $activePeriod->id)
                ->sum('amount');
        }

        $penilaianPending = DB::table('performance_assessments')
            ->where('validation_status', 'Menunggu Validasi')
            ->count();

        $kinerja = [
            'periode_aktif'            => $activePeriod,          // object period untuk ditampilkan di UI
            'total_remunerasi_periode' => $totalRemunPeriode,     // sum(amount) periode aktif
            'penilaian_pending'        => $penilaianPending,      // jumlah assessment dengan status pending validasi
        ];

        return view('super_admin.dashboard', compact('stats','review','kinerja'));
    }
}
