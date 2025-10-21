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

        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('reviews') && Schema::hasTable('review_details')) {
            $from = Carbon::now()->subDays(30)->toDateTimeString();

            $base = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->where('rd.created_at', '>=', $from);

            $review['avg_rating_unit_30d']   = (clone $base)->avg('rd.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->count();

            $review['top_staff'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('professions as p', 'p.id', '=', 'rd.profession_id')
                ->select('p.id', DB::raw('p.name as nama'), DB::raw('NULL as jabatan'),
                    DB::raw('AVG(rd.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->groupBy('p.id', 'p.name')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByDesc('avg_rating')
                ->limit(5)->get();

            $review['recent_comments'] = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->join('professions as p', 'p.id', '=', 'rd.profession_id')
                ->select('p.name as nama', 'rd.rating', 'rd.comment as komentar', 'r.created_at')
                ->whereIn('r.unit_id', $scopeUnitIds)
                ->whereNotNull('rd.comment')
                ->orderByDesc('r.created_at')
                ->limit(10)->get();
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

        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('performance_assessments') && Schema::hasTable('users')) {
            $kinerja['penilaian_pending'] = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->whereIn('u.unit_id', $scopeUnitIds)
                ->where('pa.validation_status', 'Menunggu Validasi')
                ->count();
        }

        return view('kepala_poliklinik.dashboard', compact('stats', 'review', 'kinerja'));
    }
}
