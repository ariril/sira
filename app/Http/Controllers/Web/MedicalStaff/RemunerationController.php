<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Enums\ReviewStatus;
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

        // Progress approval untuk penilaian yang sedang berjalan (UX: informatif, tidak menampilkan remunerasi yang belum selesai sebagai "tahap approval")
        $progress = collect();
        $userId = Auth::id();
        $progress = DB::table('performance_assessments as pa')
            ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
            ->where('pa.user_id', $userId)
            ->select('pa.id as assessment_id','ap.id as period_id','ap.name as period_name')
            ->orderByDesc('pa.id')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $levels = DB::table('assessment_approvals')
                    ->where('performance_assessment_id', $row->assessment_id)
                    ->select('level','status','acted_at')
                    ->orderBy('level')
                    ->get();
                $current = 'pending';
                $highestApproved = 0;
                foreach ($levels as $lv) {
                    if ($lv->status === 'approved') {
                        $highestApproved = max($highestApproved, (int)$lv->level);
                        $current = 'approved';
                    } elseif ($lv->status === 'pending') {
                        $current = 'pending';
                        break;
                    } elseif ($lv->status === 'rejected') {
                        $current = 'rejected';
                        break;
                    }
                }
                return [
                    'assessment_id' => $row->assessment_id,
                    'period_id' => $row->period_id,
                    'period_name' => $row->period_name,
                    'highestApproved' => $highestApproved,
                    'currentStatus' => $current,
                ];
            })
            ->filter(function ($it) {
                // Tampilkan hanya yang belum terbit sebagai remunerasi; jika sudah terbit, user melihat di tabel utama
                $isPublished = DB::table('remunerations')
                    ->where('user_id', Auth::id())
                    ->where('assessment_period_id', $it['period_id'])
                    ->exists();
                return !$isPublished;
            });

        return view('pegawai_medis.remunerations.index', [
            'items' => $items,
            'progress' => $progress,
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
                ->where('r.status', ReviewStatus::APPROVED->value)
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
