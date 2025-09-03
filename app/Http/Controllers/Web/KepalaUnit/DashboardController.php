<?php

namespace App\Http\Controllers\Web\KepalaUnit;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $unitId = Auth::user()->unit_kerja_id;

        $stats = [
            'total_pegawai'    => User::where('unit_kerja_id',$unitId)->count(),
            'total_dokter'     => User::where('unit_kerja_id',$unitId)->where('profesi_id','!=',null)->where('role','pegawai_medis')->count(),
            'total_admin'      => User::where('unit_kerja_id',$unitId)->where('role','administrasi')->count(),
        ];

        $review = [
            'avg_rating_unit_30d' => null,
            'total_ulasan_unit_30d' => 0,
            'top_staff' => collect(),
            'recent_comments' => collect(),
        ];

        if (Schema::hasTable('ulasan_items')) {
            $from = now()->subDays(30);

            // rata-rata rating untuk staff di unit ini
            $base = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->where('u.unit_kerja_id', $unitId)
                ->where('ui.created_at','>=',$from);

            $review['avg_rating_unit_30d']  = (clone $base)->avg('ui.rating');
            $review['total_ulasan_unit_30d'] = (clone $base)->count();

            $review['top_staff'] = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->select('u.id','u.nama','u.jabatan',
                    DB::raw('AVG(ui.rating) as avg_rating'), DB::raw('COUNT(*) as total'))
                ->where('u.unit_kerja_id',$unitId)
                ->groupBy('u.id','u.nama','u.jabatan')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_rating')
                ->limit(5)->get();

            $review['recent_comments'] = DB::table('ulasan_items as ui')
                ->join('ulasans as ul','ul.id','=','ui.ulasan_id')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->select('u.nama','ui.rating','ui.komentar','ul.created_at')
                ->where('u.unit_kerja_id',$unitId)
                ->whereNotNull('ui.komentar')
                ->orderByDesc('ul.created_at')
                ->limit(10)->get();
        }

        $kinerja = [
            'penilaian_pending' => 0,
            'periode_aktif'     => null,
        ];

        if (Schema::hasTable('periode_penilaians')) {
            $kinerja['periode_aktif'] = DB::table('periode_penilaians')->orderByDesc('id')->first();
        }
        if (Schema::hasTable('penilaian_kinerjas')) {
            $kinerja['penilaian_pending'] = DB::table('penilaian_kinerjas as pk')
                ->join('users as u', 'u.id', '=', 'pk.user_id')
                ->where('u.unit_kerja_id', $unitId)
                ->where('pk.status_validasi', 'Menunggu Validasi')
                ->count();
        }

        return view('kepala_unit.dashboard', compact('stats','review','kinerja'));
    }
}
