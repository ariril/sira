<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{User, UnitKerja, Profesi, Remunerasi, PeriodePenilaian, PenilaianKinerja};

class DataRemunerasiController extends Controller
{
    public function index(Request $req)
    {
        // --- Periode aktif / pilihan user ---
        $selectedPeriode = null;

        if ($req->filled('periode_id')) {
            $selectedPeriode = PeriodePenilaian::find($req->integer('periode_id'));
        }
        if (!$selectedPeriode) {
            $selectedPeriode = PeriodePenilaian::where('is_active', 1)->first()
                ?: PeriodePenilaian::orderByDesc('tanggal_mulai')->first();
        }

        // --- Filter lain ---
        $unitId    = $req->integer('unit_id') ?: null;
        $profesiId = $req->integer('profesi_id') ?: null;
        $q         = trim((string) $req->get('q', ''));

        // --- Base query untuk table (join remunerasi & penilaian_kinerja periode terpilih) ---
        $base = User::query()
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('profesi as pr', 'pr.id', '=', 'users.profesi_id')
            ->leftJoin('remunerasi as r', function ($j) use ($selectedPeriode) {
                $j->on('r.user_id', '=', 'users.id')
                    ->where('r.periode_penilaian_id', '=', optional($selectedPeriode)->id);
            })
            ->leftJoin('penilaian_kinerja as pk', function ($j) use ($selectedPeriode) {
                $j->on('pk.user_id', '=', 'users.id')
                    ->where('pk.periode_penilaian_id', '=', optional($selectedPeriode)->id);
            })
            ->selectRaw("
                users.id,
                users.nip,
                users.nama,
                users.jabatan,
                uk.nama_unit as unit_nama,
                pr.nama as profesi_nama,
                COALESCE(pk.skor_total_wsm, 0) as skor_wsm,
                r.nilai_remunerasi,
                r.status_pembayaran
            ");

        // Terapkan filter
        if ($unitId)    $base->where('users.unit_kerja_id', $unitId);
        if ($profesiId) $base->where('users.profesi_id', $profesiId);
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('users.nama', 'like', "%{$q}%")
                    ->orWhere('users.nip', 'like', "%{$q}%");
            });
        }

        // Urut dan paginate
        $perPage = (int) $req->get('per_page', 25);
        $rows = $base->orderBy('users.nama')->paginate($perPage)->withQueryString();

        // --- Data untuk filter dropdown ---
        $periodes = PeriodePenilaian::orderByDesc('tanggal_mulai')->get(['id','nama_periode','is_active']);
        $units    = UnitKerja::orderBy('nama_unit')->get(['id','nama_unit']);
        $profesis = Profesi::orderBy('nama')->get(['id','nama']);

        // --- Ringkasan (agregat seluruh hasil filter, bukan hanya halaman ini) ---
        $aggBase = clone $base;
        $totalPegawai = (clone $aggBase)->count('users.id');
        $avgRemun     = (clone $aggBase)->avg('r.nilai_remunerasi');
        $totalRemun   = (clone $aggBase)->sum('r.nilai_remunerasi');

        // Persentase "validasi penilaian" (pk.skor_total_wsm > 0 dianggap ada penilaian)
        $withScore    = (clone $aggBase)->where('pk.skor_total_wsm', '>', 0)->count('users.id');
        $capaiPersen  = $totalPegawai > 0 ? round($withScore / $totalPegawai * 100, 1) : 0;

        return view('pages.data-remunerasi', [
            'rows'            => $rows,
            'periodes'        => $periodes,
            'units'           => $units,
            'profesis'        => $profesis,
            'selectedPeriode' => $selectedPeriode,
            'filters'         => [
                'periode_id' => optional($selectedPeriode)->id,
                'unit_id'    => $unitId,
                'profesi_id' => $profesiId,
                'q'          => $q,
                'per_page'   => $perPage,
            ],
            'summary' => [
                'totalPegawai' => $totalPegawai,
                'avgRemun'     => $avgRemun,
                'totalRemun'   => $totalRemun,
                'capaiPersen'  => $capaiPersen,
                'periodeNama'  => optional($selectedPeriode)->nama_periode,
            ],
        ]);
    }
}
