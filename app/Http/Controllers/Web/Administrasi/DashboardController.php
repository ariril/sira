<?php

namespace App\Http\Controllers\Web\Administrasi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $unitId = Auth::user()->unit_kerja_id;

        $today = now()->startOfDay();

        $ops = [
            'antrian_hari_ini' => 0,
            'kehadiran_hari_ini' => 0,
            'jadwal_dokter_besok' => collect(),
            'ulasan_hari_ini' => 0,
        ];

        if (Schema::hasTable('antrian_pasiens')) {
            $ops['antrian_hari_ini'] = DB::table('antrian_pasiens')
                ->where('unit_kerja_id',$unitId)
                ->whereDate('created_at',$today)->count();
        }
        if (Schema::hasTable('kehadirans')) {
            $ops['kehadiran_hari_ini'] = DB::table('kehadirans')
                ->where('unit_kerja_id',$unitId)
                ->whereDate('tanggal', $today)->count();
        }
        if (Schema::hasTable('jadwal_dokters')) {
            $ops['jadwal_dokter_besok'] = DB::table('jadwal_dokters')
                ->where('unit_kerja_id',$unitId)
                ->whereDate('tanggal', now()->addDay()->toDateString())
                ->orderBy('jam_mulai')->limit(10)->get();
        }
        if (Schema::hasTable('ulasan_items')) {
            $ops['ulasan_hari_ini'] = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id')
                ->where('u.unit_kerja_id',$unitId)
                ->whereDate('ui.created_at',$today)->count();
        }

        return view('administrasi.dashboard', compact('ops'));
    }
}
