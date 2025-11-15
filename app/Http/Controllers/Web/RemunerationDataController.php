<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{User, Unit, Profession, Remuneration, AssessmentPeriod, PerformanceAssessment};

class RemunerationDataController extends Controller
{
    public function index(Request $req)
    {
        // --- Periode aktif / pilihan user ---
        $selectedPeriod = null;

        if ($req->filled('periode_id')) {
            $selectedPeriod = AssessmentPeriod::find($req->integer('periode_id'));
        }
        if (!$selectedPeriod) {
            $selectedPeriod = AssessmentPeriod::where('status', 'active')->first()
                ?: AssessmentPeriod::orderByDesc('start_date')->first();
        }

        // --- Filter lain ---
        $unitId     = $req->integer('unit_id') ?: null;
        $profession = $req->integer('profesi_id') ?: null;
        $q          = trim((string) $req->get('q', ''));

        // --- Base query (join remunerations & performance_assessments pada periode terpilih) ---
        $base = User::query()
            ->leftJoin('units as u', 'u.id', '=', 'users.unit_id')
            ->leftJoin('professions as p', 'p.id', '=', 'users.profession_id')
            ->leftJoin('remunerations as r', function ($j) use ($selectedPeriod) {
                $j->on('r.user_id', '=', 'users.id')
                    ->where('r.assessment_period_id', '=', optional($selectedPeriod)->id);
            })
            ->leftJoin('performance_assessments as pa', function ($j) use ($selectedPeriod) {
                $j->on('pa.user_id', '=', 'users.id')
                    ->where('pa.assessment_period_id', '=', optional($selectedPeriod)->id);
            })
            ->selectRaw("
                users.id,
                users.employee_number,
                users.name,
                users.position,
                u.name as unit_nama,
                p.name as profesi_nama,
                COALESCE(pa.total_wsm_score, 0) as skor_wsm,
                r.amount as nilai_remunerasi,
                r.payment_status as status_pembayaran
            ");

        // Terapkan filter
        if ($unitId)     $base->where('users.unit_id', $unitId);
        if ($profession) $base->where('users.profession_id', $profession);
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.employee_number', 'like', "%{$q}%");
            });
        }

        // Urut & paginate
        $perPage = (int) $req->get('per_page', 25);
        $rows = $base->orderBy('users.name')->paginate($perPage)->withQueryString();

        // --- Data filter dropdown ---
    $periodes = AssessmentPeriod::orderByDesc('start_date')->get(['id','name','status']);
        $units    = Unit::orderBy('name')->get(['id','name as nama_unit']);
        $profesis = Profession::orderBy('name')->get(['id','name as nama']);

        // --- Ringkasan agregat (berdasarkan filter penuh) ---
        $aggBase       = clone $base;
        $totalPegawai  = (clone $aggBase)->count('users.id');
        $avgRemun      = (clone $aggBase)->avg('r.amount');
        $totalRemun    = (clone $aggBase)->sum('r.amount');
        $withScore     = (clone $aggBase)->where('pa.total_wsm_score', '>', 0)->count('users.id');
        $capaiPersen   = $totalPegawai > 0 ? round($withScore / $totalPegawai * 100, 1) : 0;

        return view('pages.data-remunerasi', [
            'rows'            => $rows,
            'periodes'        => $periodes,
            'units'           => $units,
            'profesis'        => $profesis,
            'selectedPeriode' => $selectedPeriod, // nama variabel tetap agar Blade kamu tak berubah
            'filters'         => [
                'periode_id' => optional($selectedPeriod)->id,
                'unit_id'    => $unitId,
                'profesi_id' => $profession,
                'q'          => $q,
                'per_page'   => $perPage,
            ],
            'summary' => [
                'totalPegawai' => $totalPegawai,
                'avgRemun'     => $avgRemun,
                'totalRemun'   => $totalRemun,
                'capaiPersen'  => $capaiPersen,
                'periodeNama'  => optional($selectedPeriod)->name,
            ],
        ]);
    }
}
