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
        $user   = Auth::user();
        if (!$user) {
            abort(403); // pastikan route punya middleware('auth')
        }

        // bisa null jika belum diisi di DB
        $unitId = $user->unit_kerja_id;

        $today = now()->startOfDay();

        $pickColumn = function (string $table, array $candidates): ?string {
            foreach ($candidates as $c) {
                if (Schema::hasColumn($table, $c)) return $c;
            }
            return null;
        };

        $ops = [
            'antrian_hari_ini'    => 0,
            'kehadiran_hari_ini'  => 0,
            'jadwal_dokter_besok' => collect(),
            'ulasan_hari_ini'     => 0,
        ];

        if (Schema::hasTable('antrian_pasiens')) {
            $q = DB::table('antrian_pasiens');
            $unitCol = $pickColumn('antrian_pasiens', ['unit_kerja_id','poli_id','unit_id','poliklinik_id']);
            if ($unitCol && $unitId) $q->where($unitCol, $unitId);
            $ops['antrian_hari_ini'] = $q->whereDate('created_at', $today)->count();
        }

        if (Schema::hasTable('kehadirans')) {
            $q = DB::table('kehadirans');
            $unitCol = $pickColumn('kehadirans', ['unit_kerja_id','unit_id']);
            if ($unitCol && $unitId) $q->where($unitCol, $unitId);
            $dateCol = Schema::hasColumn('kehadirans','tanggal') ? 'tanggal' : 'created_at';
            $ops['kehadiran_hari_ini'] = $q->whereDate($dateCol, $today)->count();
        }

        if (Schema::hasTable('jadwal_dokters')) {
            $q = DB::table('jadwal_dokters');
            $unitCol = $pickColumn('jadwal_dokters', ['unit_kerja_id','poli_id','unit_id']);
            if ($unitCol && $unitId) $q->where($unitCol, $unitId);
            $dateCol = $pickColumn('jadwal_dokters', ['tanggal','tgl','date']) ?? 'tanggal';
            $ops['jadwal_dokter_besok'] = $q
                ->whereDate($dateCol, now()->addDay()->toDateString())
                ->orderBy($pickColumn('jadwal_dokters',['jam_mulai','start_time']) ?? 'jam_mulai')
                ->limit(10)->get();
        }

        if (Schema::hasTable('ulasan_items') && Schema::hasTable('users')) {
            $q = DB::table('ulasan_items as ui')
                ->join('users as u','u.id','=','ui.tenaga_medis_id');
            if ($unitId && Schema::hasColumn('users','unit_kerja_id')) {
                $q->where('u.unit_kerja_id',$unitId);
            }
            $ops['ulasan_hari_ini'] = $q->whereDate('ui.created_at',$today)->count();
        }

        $needsUnit = is_null($unitId);
        return view('administrasi.dashboard', compact('ops','needsUnit'));
    }
}
