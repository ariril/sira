<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\PerformanceAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $assessment->load(['assessmentPeriod','details.performanceCriteria', 'user.unit', 'user.profession']);

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
            ->selectRaw('AVG(review_details.rating) as avg_rating, COUNT(review_details.rating) as total_raters')
            ->join('reviews', 'reviews.id', '=', 'review_details.review_id')
            ->where('review_details.medical_staff_id', $userId)
            ->where('reviews.status', \App\Enums\ReviewStatus::APPROVED)
            ->whereNotNull('review_details.rating')
            ->when($period->start_date, fn($q)=>$q->whereDate('reviews.decided_at','>=',$period->start_date))
            ->when($period->end_date, fn($q)=>$q->whereDate('reviews.decided_at','<=',$period->end_date))
            ->first();

        $avgRating = $ratingAgg?->avg_rating ? (float)$ratingAgg->avg_rating : 0.0;
        $raterCount = $ratingAgg?->total_raters ? (int)$ratingAgg->total_raters : 0;
        $weightedRating = $avgRating * max($raterCount, 0);

        $userUnitId = $assessment->user?->unit_id;
        $userProfessionId = $assessment->user?->profession_id;

        // Peer totals per unit+profession (same period)
        $peerAttendance = \App\Models\Attendance::query()
            ->join('users as u', 'u.id', '=', 'attendances.user_id')
            ->where('u.unit_id', $userUnitId)
            ->where('u.profession_id', $userProfessionId)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->where('attendance_status', \App\Enums\AttendanceStatus::HADIR)
            ->selectRaw('COUNT(*) as days, SUM(work_duration_minutes) as minutes')
            ->first();

        $peerDiscipline = DB::table('multi_rater_assessments as mra')
            ->join('users as u', 'u.id', '=', 'mra.assessee_id')
            ->join('multi_rater_assessment_details as d', 'd.multi_rater_assessment_id', '=', 'mra.id')
            ->where('mra.assessment_period_id', $period->id)
            ->where('mra.status', 'submitted')
            ->where('u.unit_id', $userUnitId)
            ->where('u.profession_id', $userProfessionId)
            ->groupBy('mra.assessee_id')
            ->selectRaw('AVG(d.score) as avg_per_staff')
            ->get()
            ->sum('avg_per_staff');

        $peerContrib = DB::table('additional_contributions as ac')
            ->join('users as u', 'u.id', '=', 'ac.user_id')
            ->where('ac.assessment_period_id', $period->id)
            ->where('ac.validation_status', \App\Enums\ContributionValidationStatus::APPROVED)
            ->where('u.unit_id', $userUnitId)
            ->where('u.profession_id', $userProfessionId)
            ->sum('ac.score');

        $peerPatients = DB::table('criteria_metrics as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->join('performance_criterias as pc', 'pc.id', '=', 'cm.performance_criteria_id')
            ->where('cm.assessment_period_id', $period->id)
            ->where('u.unit_id', $userUnitId)
            ->where('u.profession_id', $userProfessionId)
            ->where('pc.name', 'like', '%Pasien%')
            ->sum('cm.value_numeric');

        $peerRatings = DB::table('review_details as rd')
            ->join('reviews as r', 'r.id', '=', 'rd.review_id')
            ->join('users as u', 'u.id', '=', 'rd.medical_staff_id')
            ->where('r.status', \App\Enums\ReviewStatus::APPROVED)
            ->where('u.unit_id', $userUnitId)
            ->where('u.profession_id', $userProfessionId)
            ->whereNotNull('rd.rating')
            ->when($period->start_date, fn($q)=>$q->whereDate('r.decided_at','>=',$period->start_date))
            ->when($period->end_date, fn($q)=>$q->whereDate('r.decided_at','<=',$period->end_date))
            ->groupBy('rd.medical_staff_id')
            ->selectRaw('AVG(rd.rating) as avg_rating, COUNT(rd.rating) as raters')
            ->get()
            ->sum(fn($row) => (float)$row->avg_rating * (int)$row->raters);

        $metrics = [];
        foreach ($assessment->details as $detail) {
            $criteria = $detail->performanceCriteria;
            $name = strtolower($criteria->name ?? '');
            $key = match (true) {
                str_contains($name, 'absensi') => 'absensi',
                str_contains($name, 'disiplin') || str_contains($name, 'kedisiplinan') => 'kedisiplinan',
                str_contains($name, 'kontribusi') => 'kontribusi',
                str_contains($name, 'pasien') => 'pasien',
                str_contains($name, 'rating') => 'rating',
                default => null,
            };

            if (!$key) {
                continue;
            }

            [$rawLines, $rawValue, $peerTotal] = match ($key) {
                'absensi' => [
                    [
                        ['label' => 'Total jam kerja selama periode', 'value' => number_format($workMinutes / 60, 1) . ' jam', 'hint' => 'Akumulasi durasi kerja (check-in sampai check-out) status Hadir'],
                        ['label' => 'Total kehadiran', 'value' => $attendanceDays . ' hari', 'hint' => 'Berdasarkan absensi berstatus Hadir'],
                    ],
                    $workMinutes > 0 ? $workMinutes / 60 : ($attendanceDays > 0 ? $attendanceDays : 0),
                    ($workMinutes > 0
                        ? ((int)($peerAttendance->minutes ?? 0) / 60)
                        : (int) ($peerAttendance->days ?? 0)),
                ],
                'kedisiplinan' => [
                    [
                        ['label' => 'Rata-rata skor 360', 'value' => $discipline !== null ? number_format((float)$discipline, 2) : '-', 'hint' => 'Hanya penilaian 360 berstatus submitted'],
                        ['label' => 'Jumlah penilai 360', 'value' => $disciplineCount . ' entri'],
                    ],
                    $discipline !== null ? (float)$discipline : 0,
                    $peerDiscipline,
                ],
                'kontribusi' => [
                    [
                        ['label' => 'Total poin disetujui', 'value' => number_format($contrib, 2), 'hint' => 'Penjumlahan score kontribusi Approved'],
                        ['label' => 'Jumlah item kontribusi', 'value' => $contribCount . ' item'],
                    ],
                    $contrib,
                    (float)$peerContrib,
                ],
                'pasien' => [
                    [
                        ['label' => 'Total pasien', 'value' => number_format($patients, 0) . ' pasien'],
                        ['label' => 'Jumlah entri data', 'value' => $patientCount . ' entri'],
                    ],
                    $patients,
                    (float)$peerPatients,
                ],
                'rating' => [
                    [
                        ['label' => 'Rerata rating', 'value' => number_format($avgRating, 2)],
                        ['label' => 'Jumlah rater', 'value' => $raterCount],
                        ['label' => 'Nilai mentah (rerata × rater)', 'value' => number_format($weightedRating, 2)],
                    ],
                    $weightedRating,
                    (float)$peerRatings,
                ],
                default => [[], 0, null],
            };

            $score = $detail->score !== null ? (float)$detail->score : null;
            $denominator = $peerTotal && $peerTotal > 0 ? $peerTotal : (($rawValue && $score) ? ($rawValue / ($score / 100)) : null);

            $metrics[$criteria->id] = [
                'title' => $criteria->name ?? 'Data mentah',
                'lines' => $rawLines,
                'formula' => [
                    'raw' => $rawValue,
                    'denominator' => $denominator,
                    'result' => $score,
                ],
            ];
        }

        return $metrics;
    }
}
