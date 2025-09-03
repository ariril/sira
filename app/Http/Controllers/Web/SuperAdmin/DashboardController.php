<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UnitKerja;
use App\Models\Profesi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_user'     => User::count(),
            'total_unit'     => UnitKerja::count(),
            'total_profesi'  => Profesi::count(),
            'pegawai_medis'  => User::where('role','pegawai_medis')->count(),
            'kepala_unit'    => User::where('role','kepala_unit')->count(),
            'administrasi'   => User::where('role','administrasi')->count(),
            'super_admin'    => User::where('role','super_admin')->count(),
            'unverified'     => User::whereNull('email_verified_at')->count(),
        ];

        // Review / rating (opsional)
        $review = [
            'avg_rating_30d' => null,
            'total_30d'      => 0,
            'top_tenaga_medis' => collect(),
            'rating_per_unit'  => collect(),
        ];

        if (Schema::hasTable('ulasan_items')) {
            $from = now()->subDays(30);

            $review['avg_rating_30d'] = DB::table('ulasan_items')
                ->where('created_at', '>=', $from)
                ->avg('rating');

            $review['total_30d'] = DB::table('ulasan_items')
                ->where('created_at', '>=', $from)
                ->count();

            // Top 5 tenaga medis (min 3 ulasan)
            $review['top_tenaga_medis'] = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->select('u.id','u.nama','u.jabatan',
                    DB::raw('AVG(ui.rating) as avg_rating'),
                    DB::raw('COUNT(*) as total_ulasan'))
                ->groupBy('u.id','u.nama','u.jabatan')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_rating')
                ->limit(5)
                ->get();

            // Rata-rata rating per unit
            $review['rating_per_unit'] = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->join('unit_kerjas as uk','uk.id','=','u.unit_kerja_id')
                ->select('uk.nama_unit', DB::raw('AVG(ui.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->groupBy('uk.nama_unit')
                ->orderByDesc('avg_rating')
                ->get();
        }

        // Remunerasi & Penilaian (opsional)
        $kinerja = [
            'periode_aktif'  => null,
            'total_remunerasi_periode' => null,
            'penilaian_pending' => 0,
        ];

        if (Schema::hasTable('periode_penilaians')) {
            $kinerja['periode_aktif'] = DB::table('periode_penilaians')
                ->orderByDesc('id')->first();
        }
        if (Schema::hasTable('remunerasis') && $kinerja['periode_aktif']) {
            $kinerja['total_remunerasi_periode'] = DB::table('remunerasis')
                ->where('periode_id', $kinerja['periode_aktif']->id)->sum('total');
        }
        if (Schema::hasTable('penilaian_kinerjas')) {
            $kinerja['penilaian_pending'] = DB::table('penilaian_kinerjas')
                ->where('status_validasi', 'Menunggu Validasi')
                ->count();
        }

        return view('super_admin.dashboard', compact('stats','review','kinerja'));
    }
}
