<?php

namespace App\Http\Controllers\Web\UnitHead;

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
        $unitId = optional(Auth::user())->unit_id;

        $stats = [
            'total_pegawai' => User::where('unit_id', $unitId)->count(),
            'total_dokter'  => User::where('unit_id', $unitId)
                ->whereNotNull('profession_id')
                ->where('role', 'pegawai_medis')->count(),
            'total_admin'   => User::where('unit_id', $unitId)
                ->where('role', 'administrasi')->count(),
        ];

        $review = [
            'avg_rating_unit_30d'   => null,
            'total_ulasan_unit_30d' => 0,
            'top_staff'             => collect(),   // diisi "top professions" agar UI tetap dapat data
            'recent_comments'       => collect(),
        ];

        if ($unitId && Schema::hasTable('reviews') && Schema::hasTable('review_details')) {
            $from = Carbon::now()->subDays(30)->toDateTimeString();

            // Basis ulasan per unit (join reviews -> review_details)
            $base = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->where('r.unit_id', $unitId)
                ->where('rd.created_at', '>=', $from);

            $review['avg_rating_unit_30d']   = (clone $base)->avg('rd.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->count();

            // "top_staff" → top professions (min 3 review)
            $review['top_staff'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('professions as p', 'p.id', '=', 'rd.profession_id')
                ->select(
                    'p.id',
                    DB::raw('p.name as nama'),
                    DB::raw('NULL as jabatan'),
                    DB::raw('AVG(rd.rating) as avg_rating'),
                    DB::raw('COUNT(*) as total')
                )
                ->where('r.unit_id', $unitId)
                ->groupBy('p.id', 'p.name')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_rating')
                ->limit(5)
                ->get();

            // Komentar terbaru
            $review['recent_comments'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('professions as p', 'p.id', '=', 'rd.profession_id')
                ->select('p.name as nama', 'rd.rating', 'rd.comment as komentar', 'r.created_at')
                ->where('r.unit_id', $unitId)
                ->whereNotNull('rd.comment')
                ->orderByDesc('r.created_at')
                ->limit(10)
                ->get();
        }

        $kinerja = [
            'penilaian_pending' => 0,
            'periode_aktif'     => null,
        ];

        if (Schema::hasTable('assessment_periods')) {
            $kinerja['periode_aktif'] = DB::table('assessment_periods')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();
        }

        if ($unitId && Schema::hasTable('performance_assessments') && Schema::hasTable('users')) {
            $kinerja['penilaian_pending'] = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->where('u.unit_id', $unitId)
                ->where('pa.validation_status', 'Menunggu Validasi')
                ->count();
        }

        return view('kepala_unit.dashboard', compact('stats', 'review', 'kinerja'));
    }
}
