<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ContributionValidationStatus;
use App\Enums\ReviewStatus;
use App\Models\AdditionalContribution;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\CriteriaMetric;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\ReviewDetail;

class BestScenarioCalculator
{
    /**
     * Static weight (percent) for each criteria in the Best Scenario.
     * Total weight = 100 so the sum of WSM scores across all employees per unit is 100.
     */
    private const DEFAULT_WEIGHT = 20.0;

    /** Criteria configuration (order preserved for UI). */
    private const CRITERIA = [
        'absensi'      => 'Absensi',
        'kedisiplinan' => 'Kedisiplinan (360)',
        'kontribusi'   => 'Kontribusi Tambahan',
        'pasien'       => 'Jumlah Pasien Ditangani',
        'rating'       => 'Rating',
    ];

    /**
     * Build Best Scenario WSM scores for a unit & period.
     * @param int $unitId Target unit (matches allocation unit).
     * @param AssessmentPeriod $period Period window (start/end used for filters).
     * @param array<int> $userIds Pegawai medis IDs in the unit.
     * @return array{
     *   users: array<int, array{criteria: array<int, array{key:string,label:string,raw:float,normalized:float,weight:float,weighted:float,criteria_total:float}>, total_wsm: float}>,
     *   unit_total: float,
     *   weights: array<string,float>,
     *   criteria_totals: array<string,float>
     * }
     */
    public function calculateForUnit(int $unitId, AssessmentPeriod $period, array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if (empty($userIds)) {
            return [
                'users' => [],
                'unit_total' => 0.0,
                'weights' => $this->weights(),
                'criteria_totals' => [],
            ];
        }

        $raw = [
            'absensi'      => $this->collectAttendance($period, $userIds),
            'kedisiplinan' => $this->collectDiscipline($period, $userIds),
            'kontribusi'   => $this->collectContributionPoints($period, $userIds, $unitId),
            'pasien'       => $this->collectPatientCount($period, $userIds),
            'rating'       => $this->collectRatings($period, $userIds, $unitId),
        ];

        $totalsByCriteria = [];
        $normalized = [];
        foreach (self::CRITERIA as $key => $label) {
            [$norm, $total] = $this->normalizeColumn($raw[$key] ?? [], $userIds);
            $normalized[$key] = $norm;
            $totalsByCriteria[$key] = $total;
        }

        $activeKeys = $this->determineActiveCriteria($totalsByCriteria);
        $weights = $this->weights(count($activeKeys));
        $users = [];
        $unitTotal = 0.0;
        foreach ($userIds as $uid) {
            $criteriaRows = [];
            $sum = 0.0;
            foreach ($activeKeys as $key) {
                $label = self::CRITERIA[$key];
                $normVal = (float)($normalized[$key][$uid] ?? 0.0);
                $weighted = $normVal * ($weights[$key] / 100.0);
                $sum += $weighted;
                $criteriaRows[] = [
                    'key'            => $key,
                    'label'          => $label,
                    'raw'            => (float)($raw[$key][$uid] ?? 0.0),
                    'normalized'     => round($normVal, 6),
                    'weight'         => $weights[$key],
                    'weighted'       => round($weighted, 6),
                    'criteria_total' => round((float)($totalsByCriteria[$key] ?? 0.0), 4),
                    'type'           => $key === 'kedisiplinan' ? 'benefit' : 'benefit',
                ];
            }
            $users[$uid] = [
                'criteria'  => $criteriaRows,
                'total_wsm' => round($sum, 6),
            ];
            $unitTotal += $sum;
        }

        return [
            'users'           => $users,
            'unit_total'      => round($unitTotal, 6),
            'weights'         => $weights,
            'criteria_totals' => $totalsByCriteria,
            'criteria_used'   => $activeKeys,
        ];
    }

    /** @return array<string,float> */
    private function weights(int $activeCount = null): array
    {
        $count = $activeCount ?? count(self::CRITERIA);
        $count = max(1, $count);
        $per = 100.0 / $count;
        $weights = [];
        foreach (self::CRITERIA as $key => $label) {
            $weights[$key] = $per;
        }
        return $weights;
    }

    /** @param array<int,float|int> $raw */
    private function normalizeColumn(array $raw, array $userIds): array
    {
        $total = 0.0;
        foreach ($userIds as $uid) {
            $total += (float)($raw[$uid] ?? 0);
        }

        $norm = [];
        foreach ($userIds as $uid) {
            $value = (float)($raw[$uid] ?? 0.0);
            $norm[$uid] = $total > 0 ? ($value / $total) * 100.0 : 0.0;
        }
        return [$norm, $total];
    }

    /**
     * Determine which criteria are active for a unit/period.
     * Rules: include criteria with total > 0; if none, include all (fallback).
     * @param array<string,float> $totals
     * @return array<int,string> keys of criteria used
     */
    private function determineActiveCriteria(array $totals): array
    {
        $active = [];
        foreach (self::CRITERIA as $key => $label) {
            if (($totals[$key] ?? 0) > 0) {
                $active[] = $key;
            }
        }
        if (empty($active)) {
            $active = array_keys(self::CRITERIA);
        }
        return $active;
    }

    /** @return array<int,float> */
    private function collectAttendance(AssessmentPeriod $period, array $userIds): array
    {
        if (!$period->start_date || !$period->end_date) {
            return [];
        }

        return Attendance::query()
            ->selectRaw('user_id, COUNT(*) as total_hadir')
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->where('attendance_status', AttendanceStatus::HADIR)
            ->groupBy('user_id')
            ->pluck('total_hadir', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();
    }

    /** @return array<int,float> */
    private function collectDiscipline(AssessmentPeriod $period, array $userIds): array
    {
        $criteriaId = $this->resolveDisciplineCriteriaId();
        if (!$criteriaId) {
            return [];
        }

        return MultiRaterAssessmentDetail::query()
            ->selectRaw('mra.assessee_id as user_id, AVG(mrad.score) as avg_score')
            ->from('multi_rater_assessment_details as mrad')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'mrad.multi_rater_assessment_id')
            ->where('mra.assessment_period_id', $period->id)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('mrad.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id')
            ->pluck('avg_score', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();
    }

    private function resolveDisciplineCriteriaId(): ?int
    {
        $named = PerformanceCriteria::where('name', 'like', '%Kedisiplinan%')->value('id');
        if ($named) {
            return (int) $named;
        }
        $first360 = PerformanceCriteria::where('is_360', true)->where('is_active', true)->orderBy('id')->value('id');
        return $first360 ? (int) $first360 : null;
    }

    /** @return array<int,float> */
    private function collectContributionPoints(AssessmentPeriod $period, array $userIds, int $unitId): array
    {
        $claimPoints = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->selectRaw('c.user_id, COALESCE(SUM(COALESCE(c.awarded_points, t.points, 0)),0) as total_score')
            ->whereIn('c.user_id', $userIds)
            ->where('t.assessment_period_id', $period->id)
            ->where('t.unit_id', $unitId)
            ->whereIn('c.status', ['approved', 'completed'])
            ->groupBy('c.user_id')
            ->pluck('total_score', 'c.user_id')
            ->map(fn($v) => (float)$v)
            ->all();

        $adhocPoints = AdditionalContribution::query()
            ->selectRaw('user_id, COALESCE(SUM(score),0) as total_score')
            ->whereIn('user_id', $userIds)
            ->where('assessment_period_id', $period->id)
            ->where('validation_status', ContributionValidationStatus::APPROVED)
            ->whereNull('claim_id')
            ->groupBy('user_id')
            ->pluck('total_score', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();

        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $out[$uid] = (float)($claimPoints[$uid] ?? 0) + (float)($adhocPoints[$uid] ?? 0);
        }
        return $out;
    }

    /** @return array<int,float> */
    private function collectPatientCount(AssessmentPeriod $period, array $userIds): array
    {
        $criteriaId = $this->resolvePatientCriteriaId();
        if (!$criteriaId) {
            return [];
        }

        return CriteriaMetric::query()
            ->selectRaw('user_id, COALESCE(SUM(value_numeric),0) as total_value')
            ->where('assessment_period_id', $period->id)
            ->where('performance_criteria_id', $criteriaId)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('total_value', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();
    }

    private function resolvePatientCriteriaId(): ?int
    {
        $named = PerformanceCriteria::where('name', 'like', '%Pasien%')->value('id');
        return $named ? (int) $named : null;
    }

    /** @return array<int,float> */
    private function collectRatings(AssessmentPeriod $period, array $userIds, int $unitId): array
    {
        $start = $period->start_date;
        $end = $period->end_date;

        return ReviewDetail::query()
            ->selectRaw('review_details.medical_staff_id as user_id, AVG(review_details.rating) as avg_rating')
            ->join('reviews', 'reviews.id', '=', 'review_details.review_id')
            ->where('reviews.status', ReviewStatus::APPROVED)
            ->where('reviews.unit_id', $unitId)
            ->whereIn('review_details.medical_staff_id', $userIds)
            ->when($start, fn($q) => $q->whereDate('reviews.decided_at', '>=', $start))
            ->when($end, fn($q) => $q->whereDate('reviews.decided_at', '<=', $end))
            ->groupBy('review_details.medical_staff_id')
            ->pluck('avg_rating', 'user_id')
            ->map(fn($v) => (float)$v)
            ->all();
    }
}
