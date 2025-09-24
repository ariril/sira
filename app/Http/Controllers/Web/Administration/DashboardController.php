<?php

namespace App\Http\Controllers\Web\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        if (!$user) abort(403);

        $today  = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // isikan metrik agar UI lama tetap jalan (keys dipertahankan)
        $ops = [
            // Gantikan "antrian_hari_ini" dengan jumlah assessment yang dibuat/hari ini
            'antrian_hari_ini'    => Schema::hasTable('performance_assessments')
                ? DB::table('performance_assessments')->whereDate('created_at', $today)->count()
                : 0,

            // Kehadiran hari ini
            'kehadiran_hari_ini'  => Schema::hasTable('attendances')
                ? DB::table('attendances')->whereDate('attendance_date', $today)->count()
                : 0,

            // Gantikan "jadwal_dokter_besok" → daftar assessment besok (koleksi untuk dipajang)
            'jadwal_dokter_besok' => Schema::hasTable('performance_assessments') && Schema::hasTable('users')
                ? DB::table('performance_assessments as pa')
                    ->join('users as u', 'u.id', '=', 'pa.user_id')
                    ->select('pa.id', 'pa.assessment_date', 'u.name as dokter', 'u.position')
                    ->whereDate('pa.assessment_date', $tomorrow)
                    ->orderBy('pa.assessment_date')
                    ->limit(10)
                    ->get()
                : collect(),

            // Ulasan yang masuk hari ini (dari reviews)
            'ulasan_hari_ini'     => Schema::hasTable('reviews')
                ? DB::table('reviews')->whereDate('created_at', $today)->count()
                : 0,
        ];

        // UI lama punya flag ini — kita set false saja (admin tidak perlu unit binding)
        $needsUnit = false;

        return view('administrasi.dashboard', compact('ops', 'needsUnit'));
    }
}
