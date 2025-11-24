<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{User, Unit, Profession, Remuneration, AssessmentPeriod, PerformanceAssessment};

class RemunerationDataController extends Controller
{
    public function index(Request $req)
    {
        // Periode yang dipilih atau aktif
        $selectedPeriod = null;
        if ($req->filled('periode_id')) {
            $selectedPeriod = AssessmentPeriod::find($req->integer('periode_id'));
        }
        if (!$selectedPeriod) {
            $selectedPeriod = AssessmentPeriod::where('status', 'active')->first()
                ?: AssessmentPeriod::orderByDesc('start_date')->first();
        }

        // Filter lain
        $unitId     = $req->integer('unit_id') ?: null;
        $profession = $req->integer('profesi_id') ?: null;
        $q          = trim((string) $req->get('q', ''));

        $base = User::query()
            // Hanya tampilkan pegawai yang memiliki role Pegawai Medis (via pivot)
            ->whereExists(function($q){
                $q->selectRaw(1)
                  ->from('role_user as ru')
                  ->join('roles as r','r.id','=','ru.role_id')
                  ->whereColumn('ru.user_id','users.id')
                  ->where('r.slug', User::ROLE_PEGAWAI_MEDIS);
            })
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

        if ($unitId)     $base->where('users.unit_id', $unitId);
        if ($profession) $base->where('users.profession_id', $profession);
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.employee_number', 'like', "%{$q}%");
            });
        }

        $perPage = (int) $req->get('per_page', 25);
        $rows = $base->orderBy('users.name')->paginate($perPage)->withQueryString();

    $periodes = AssessmentPeriod::orderByDesc('start_date')->get(['id','name','status']);
        $units    = Unit::orderBy('name')->get(['id','name as nama_unit']);
        $profesis = Profession::orderBy('name')->get(['id','name as nama']);

        $aggBase       = clone $base;
        $totalPegawai  = (clone $aggBase)->count('users.id');
        $avgRemun      = (clone $aggBase)->avg('r.amount');
        $totalRemun    = (clone $aggBase)->sum('r.amount');
        $withScore     = (clone $aggBase)->where('pa.total_wsm_score', '>', 0)->count('users.id');
        $capaiPersen   = $totalPegawai > 0 ? round($withScore / $totalPegawai * 100, 1) : 0;

        return view('pegawai_medis.remuneration_data.index', [
            'rows'            => $rows,
            'periodes'        => $periodes,
            'units'           => $units,
            'profesis'        => $profesis,
            'selectedPeriode' => $selectedPeriod,
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
