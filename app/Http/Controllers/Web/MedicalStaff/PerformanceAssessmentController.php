<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\PerformanceAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerformanceAssessmentController extends Controller
{
    /**
     * List assessments owned by the logged-in medical staff.
     */
    public function index(Request $request)
    {
        // Acknowledge approval banner (one-time, no DB change)
        if ($request->boolean('ack_approval') && $request->filled('assessment_id')) {
            session(['approval_seen_' . (int)$request->input('assessment_id') => true]);
            return redirect()->route('pegawai_medis.assessments.index');
        }
        $assessments = PerformanceAssessment::with('assessmentPeriod')
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(10);

        return view('pegawai_medis.assessments.index', compact('assessments'));
    }

    /**
     * Show a single assessment (read-only with details).
     */
    public function show(PerformanceAssessment $assessment): View
    {
        $this->authorizeSelf($assessment);
        $assessment->load(['assessmentPeriod','details.performanceCriteria']);

        $rawMetrics = $this->buildRawMetrics($assessment);

        return view('pegawai_medis.assessments.show', compact('assessment','rawMetrics'));
    }

    /**
     * Ensure the record belongs to the logged-in user.
     * If $editable is true, also block when status is VALIDATED.
     */
    private function authorizeSelf(PerformanceAssessment $assessment): void
    {
        abort_unless($assessment->user_id === Auth::id(), 403);
    }

    /**
     * Kumpulkan data mentah per kriteria (pra-normalisasi) agar ditampilkan ke pengguna.
     * Menggunakan rumus domain saat ini:
     * - Absensi: total hari Hadir dalam periode.
     * - Kedisiplinan (360): rata-rata skor 360 (submitted) dalam periode.
     * - Kontribusi Tambahan: total poin (score) kontribusi Disetujui pada periode.
     * - Jumlah Pasien Ditangani: total value_numeric metric pada periode.
     * - Rating: (rerata rating) * (jumlah rater) dari review approved pada periode.
     */
    private function buildRawMetrics(PerformanceAssessment $assessment): array
    {
        $period = $assessment->assessmentPeriod;
        if (!$period) {
            return [];
        }

        $userId = $assessment->user_id;

        $attendanceQuery = \App\Models\Attendance::query()
            ->where('user_id', $userId)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->where('attendance_status', \App\Enums\AttendanceStatus::HADIR);

        $attendanceDays = $attendanceQuery->count();
        $workMinutes = (int) $attendanceQuery->sum('work_duration_minutes');

        $disciplineQuery = \App\Models\MultiRaterAssessmentDetail::query()
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
            ->where('mra.assessment_period_id', $period->id)
            ->where('mra.assessee_id', $userId)
            ->where('mra.status', 'submitted');

        $discipline = $disciplineQuery
            ->selectRaw('AVG(multi_rater_assessment_details.score) as avg_score')
            ->value('avg_score');
        $disciplineCount = (int) $disciplineQuery->count();

        $contribQuery = \App\Models\AdditionalContribution::query()
            ->where('user_id', $userId)
            ->where('assessment_period_id', $period->id)
            ->where('validation_status', \App\Enums\ContributionValidationStatus::APPROVED);

        $contrib = (float) $contribQuery->sum('score');
        $contribCount = (int) $contribQuery->count();

        $patientQuery = \App\Models\CriteriaMetric::query()
            ->where('user_id', $userId)
            ->where('assessment_period_id', $period->id)
            ->whereHas('criteria', fn($q) => $q->where('name', 'like', '%Pasien%'));

        $patients = (float) $patientQuery->sum('value_numeric');
        $patientCount = (int) $patientQuery->count();

        $ratingAgg = \App\Models\ReviewDetail::query()
            ->selectRaw('AVG(review_details.rating) as avg_rating, COUNT(*) as total_raters')
            ->join('reviews', 'reviews.id', '=', 'review_details.review_id')
            ->where('review_details.medical_staff_id', $userId)
            ->where('reviews.status', \App\Enums\ReviewStatus::APPROVED)
            ->when($period->start_date, fn($q)=>$q->whereDate('reviews.decided_at','>=',$period->start_date))
            ->when($period->end_date, fn($q)=>$q->whereDate('reviews.decided_at','<=',$period->end_date))
            ->first();

        $avgRating = $ratingAgg?->avg_rating ? (float)$ratingAgg->avg_rating : 0.0;
        $raterCount = $ratingAgg?->total_raters ? (int)$ratingAgg->total_raters : 0;
        $weightedRating = $avgRating * max($raterCount, 0);

        return [
            'absensi' => [
                'title' => 'Absensi & Jam Kerja',
                'lines' => [
                    ['label' => 'Total jam kerja selama periode', 'value' => number_format($workMinutes / 60, 1) . ' jam', 'hint' => 'Akumulasi dari durasi kerja (check-in sampai check-out) status Hadir'],
                    ['label' => 'Total kehadiran', 'value' => $attendanceDays . ' hari', 'hint' => 'Berdasarkan absensi dengan status Hadir'],
                ],
            ],
            'kedisiplinan' => [
                'title' => 'Kedisiplinan (360)',
                'lines' => [
                    ['label' => 'Rata-rata skor 360', 'value' => $discipline !== null ? number_format((float)$discipline, 2) : '-', 'hint' => 'Hanya penilaian 360 yang berstatus submitted'],
                    ['label' => 'Jumlah penilai 360', 'value' => $disciplineCount . ' entri'],
                ],
            ],
            'kontribusi' => [
                'title' => 'Kontribusi Tambahan',
                'lines' => [
                    ['label' => 'Total poin disetujui', 'value' => number_format($contrib, 2), 'hint' => 'Penjumlahan score kontribusi dengan status Approved'],
                    ['label' => 'Jumlah item kontribusi', 'value' => $contribCount . ' item'],
                ],
            ],
            'pasien' => [
                'title' => 'Jumlah Pasien Ditangani',
                'lines' => [
                    ['label' => 'Total pasien', 'value' => number_format($patients, 0) . ' pasien'],
                    ['label' => 'Jumlah entri data', 'value' => $patientCount . ' entri'],
                ],
            ],
            'rating' => [
                'title' => 'Rating Pelayanan',
                'lines' => [
                    ['label' => 'Rerata rating', 'value' => number_format($avgRating, 2)],
                    ['label' => 'Jumlah rater', 'value' => $raterCount],
                    ['label' => 'Perhitungan ke WSM', 'value' => number_format($weightedRating, 2), 'hint' => 'Rerata rating × jumlah rater'],
                ],
            ],
        ];
    }
}
