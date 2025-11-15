<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\Remuneration;
use App\Models\AdditionalContribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $items = Remuneration::with('assessmentPeriod')
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('pegawai_medis.remunerations.index', [
            'items' => $items,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $id): View
    {
        $remuneration = $id; // implicit model binding
        abort_unless($remuneration->user_id === Auth::id(), 403);
        $remuneration->load(['assessmentPeriod']);

        $period = $remuneration->assessmentPeriod;
        $userId = Auth::id();

        // Kontribusi tambahan pada periode remunerasi ini
        $contributions = AdditionalContribution::query()
            ->where('user_id', $userId)
            ->where('assessment_period_id', optional($period)->id)
            ->orderBy('submission_date', 'desc')
            ->get(['id','title','validation_status']);

        // Jumlah review pada rentang tanggal periode
        $reviewCount = 0;
        if ($period && $period->start_date && $period->end_date) {
            $reviewCount = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->where('rd.medical_staff_id', $userId)
                ->whereBetween('r.created_at', [
                    $period->start_date . ' 00:00:00',
                    $period->end_date   . ' 23:59:59',
                ])
                ->count();
        }

        $calc = $remuneration->calculation_details ?? [];
        $patientsHandled = data_get($calc, 'komponen.pasien_ditangani.jumlah');

        return view('pegawai_medis.remunerations.show', [
            'item' => $remuneration,
            'reviewCount' => $reviewCount,
            'contributions' => $contributions,
            'patientsHandled' => $patientsHandled,
            'calc' => $calc,
        ]);
    }
}
