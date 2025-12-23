<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ContributionValidationStatus;
use App\Enums\ReviewStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PeriodPerformanceAssessmentService
{
    /**
     * Ensure PerformanceAssessment (Penilaian Saya) exists for ALL pegawai medis in a period,
     * then (re)calculate normalized scores + total_wsm_score.
     */
    public function initializeForPeriod(AssessmentPeriod $period): void
    {
        $this->recalculateForPeriod($period);
    }

    public function recalculateForPeriodId(int $periodId): void
    {
        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            return;
        }
        $this->recalculateForPeriod($period);
    }

    public function recalculateForGroup(int $periodId, ?int $unitId, ?int $professionId): void
    {
        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            return;
        }

        $users = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->when($professionId, fn($q) => $q->where('profession_id', $professionId))
            ->get(['id', 'unit_id', 'profession_id']);

        if ($users->isEmpty()) {
            return;
        }

        $this->recalculateForUsers($period, $users->all());
    }

    private function recalculateForPeriod(AssessmentPeriod $period): void
    {
        $users = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->get(['id', 'unit_id', 'profession_id']);

        if ($users->isEmpty()) {
            return;
        }

        $this->recalculateForUsers($period, $users->all());
    }

    /**
     * @param array<int, array{id:int,unit_id:?int,profession_id:?int}> $users
     */
    private function recalculateForUsers(AssessmentPeriod $period, array $users): void
    {
        // Resolve criteria IDs (based on naming convention used across the app and seeder)
        $criteriaIds = $this->resolveCriteriaIds();

        // Group users by unit+profession as required by the business rule
        $groups = [];
        foreach ($users as $u) {
            $unitId = $u['unit_id'] ?? null;
            $professionId = $u['profession_id'] ?? null;
            $key = ($unitId ?? 'null') . '|' . ($professionId ?? 'null');
            $groups[$key] ??= [
                'unit_id' => $unitId,
                'profession_id' => $professionId,
                'user_ids' => [],
            ];
            $groups[$key]['user_ids'][] = (int) $u['id'];
        }

        foreach ($groups as $g) {
            $this->recalculateGroup($period, (array) $criteriaIds, (array) $g['user_ids'], $g['unit_id'], $g['profession_id']);
        }
    }

    /**
     * @return array{absensi:?int,kedisiplinan:?int,keterlambatan:?int,kontribusi:?int,pasien:?int,komplain:?int,rating:?int}
     */
    private function resolveCriteriaIds(): array
    {
        $absensi = PerformanceCriteria::query()->where('name', 'Absensi')->orderBy('id')->value('id');
        $kontribusi = PerformanceCriteria::query()->where('name', 'Kontribusi Tambahan')->orderBy('id')->value('id');
        $pasien = PerformanceCriteria::query()->where('name', 'Jumlah Pasien Ditangani')->orderBy('id')->value('id');
        $komplain = PerformanceCriteria::query()->where('name', 'Jumlah Komplain Pasien')->orderBy('id')->value('id');
        $rating = PerformanceCriteria::query()->where('name', 'Rating')->orderBy('id')->value('id');

        // Prefer explicit "Kedisiplinan (360)"; fallback to any 360 criteria
        $kedis = PerformanceCriteria::query()->where('name', 'like', '%Kedisiplinan%')->orderBy('id')->value('id');
        if (!$kedis) {
            $kedis = PerformanceCriteria::query()->where('input_method', '360')->orderBy('id')->value('id');
        }

        $kerjasama = PerformanceCriteria::query()->where('name', 'Kerjasama (360)')->orderBy('id')->value('id');

        return [
            'absensi' => $absensi ? (int) $absensi : null,
            'kedisiplinan' => $kedis ? (int) $kedis : null,
            'kerjasama' => $kerjasama ? (int) $kerjasama : null,
            'kontribusi' => $kontribusi ? (int) $kontribusi : null,
            'pasien' => $pasien ? (int) $pasien : null,
            'komplain' => $komplain ? (int) $komplain : null,
            'rating' => $rating ? (int) $rating : null,
        ];
    }

    /**
    * @param array{absensi:?int,kedisiplinan:?int,keterlambatan:?int,kontribusi:?int,pasien:?int,komplain:?int,rating:?int} $criteriaIds
     * @param array<int> $userIds
     */
    private function recalculateGroup(AssessmentPeriod $period, array $criteriaIds, array $userIds, ?int $unitId, ?int $professionId): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        // Ensure PerformanceAssessment exists for each user in this period
        DB::transaction(function () use ($period, $criteriaIds, $userIds, $unitId, $professionId) {
            foreach ($userIds as $uid) {
                PerformanceAssessment::firstOrCreate(
                    ['user_id' => $uid, 'assessment_period_id' => $period->id],
                    [
                        'assessment_date' => $period->end_date ?? now()->toDateString(),
                        'total_wsm_score' => 0,
                        'validation_status' => 'Menunggu Validasi',
                        'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                    ]
                );
            }

            [$raw, $totals] = $this->collectRawAndTotals($period, $criteriaIds, $userIds, $unitId);

            $activeKeys = array_values(array_filter(array_keys($totals), fn($k) => ($totals[$k] ?? 0) > 0));
            if (empty($activeKeys)) {
                $activeKeys = array_keys($totals);
            }

            // Map assessment ids for fast upsert
            $assessmentMap = PerformanceAssessment::query()
                ->where('assessment_period_id', $period->id)
                ->whereIn('user_id', $userIds)
                ->pluck('id', 'user_id');

            $criteriaTypeByKey = [];
            $ids = array_values(array_filter(array_map('intval', array_values($criteriaIds))));
            if (!empty($ids)) {
                $typeRows = PerformanceCriteria::query()->whereIn('id', $ids)->get(['id', 'type']);
                $typeById = [];
                foreach ($typeRows as $row) {
                    $typeById[(int) $row->id] = $row->type?->value ?? (string) $row->type;
                }
                foreach ($criteriaIds as $key => $cid) {
                    if (!$cid) continue;
                    $criteriaTypeByKey[$key] = $typeById[(int) $cid] ?? 'benefit';
                }
            }

            foreach ($userIds as $uid) {
                $assessmentId = (int) ($assessmentMap[$uid] ?? 0);
                if (!$assessmentId) {
                    continue;
                }

                $scores = [];
                foreach ($totals as $key => $total) {
                    $value = (float)($raw[$key][$uid] ?? 0.0);
                    if ($total <= 0) {
                        $scores[$key] = 0.0;
                        continue;
                    }
                    $ratio = $value / $total;
                    $ratio = max(0.0, min(1.0, $ratio));
                    $score = $ratio * 100.0;
                    if (($criteriaTypeByKey[$key] ?? 'benefit') === 'cost') {
                        $score = (1.0 - $ratio) * 100.0;
                    }
                    $scores[$key] = max(0.0, min(100.0, $score));
                }

                $sum = 0.0;
                foreach ($activeKeys as $k) {
                    $sum += (float)($scores[$k] ?? 0.0);
                }
                $totalWsm = count($activeKeys) ? ($sum / count($activeKeys)) : 0.0;

                PerformanceAssessment::query()
                    ->where('id', $assessmentId)
                    ->update([
                        'assessment_date' => $period->end_date ?? now()->toDateString(),
                        'total_wsm_score' => round($totalWsm, 2),
                        'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                    ]);

                foreach ($criteriaIds as $key => $criteriaId) {
                    if (!$criteriaId) {
                        continue;
                    }
                    $score = round((float)($scores[$key] ?? 0.0), 2);
                    PerformanceAssessmentDetail::updateOrCreate(
                        [
                            'performance_assessment_id' => $assessmentId,
                            'performance_criteria_id' => $criteriaId,
                        ],
                        [
                            'criteria_metric_id' => null,
                            'score' => $score,
                        ]
                    );
                }
            }
        });
    }

    /**
        * @param array{absensi:?int,kedisiplinan:?int,keterlambatan:?int,kontribusi:?int,pasien:?int,komplain:?int,rating:?int} $criteriaIds
     * @param array<int> $userIds
     * @return array{0: array<string, array<int, float>>, 1: array<string, float>}
     */
    private function collectRawAndTotals(AssessmentPeriod $period, array $criteriaIds, array $userIds, ?int $unitId): array
    {
        $start = $period->start_date;
        $end = $period->end_date;

        $raw = [
            'absensi' => [],
            'kedisiplinan' => [],
            'kerjasama' => [],
            'kontribusi' => [],
            'pasien' => [],
            'komplain' => [],
            'rating' => [],
        ];

        // Absensi: COUNT attendances Hadir within date range
        if ($start && $end) {
            $raw['absensi'] = DB::table('attendances')
                ->selectRaw('user_id, COUNT(*) as total_hadir')
                ->whereIn('user_id', $userIds)
                ->whereBetween('attendance_date', [$start, $end])
                ->where('attendance_status', AttendanceStatus::HADIR->value)
                ->groupBy('user_id')
                ->pluck('total_hadir', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();
        }

        // Kedisiplinan (360): AVG score for the discipline criteria, submitted, by assessee
        if (!empty($criteriaIds['kedisiplinan'])) {
            $cid = (int) $criteriaIds['kedisiplinan'];
            $fromDetails = DB::table('multi_rater_assessment_details as d')
                ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
                ->selectRaw('mra.assessee_id as user_id, AVG(d.score) as avg_score')
                ->where('mra.assessment_period_id', $period->id)
                ->where('mra.status', 'submitted')
                ->whereIn('mra.assessee_id', $userIds)
                ->where('d.performance_criteria_id', $cid)
                ->groupBy('mra.assessee_id')
                ->pluck('avg_score', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();

            // Fallback: simple 360 form saves to multi_rater_scores (no submitted status)
            $fromScores = DB::table('multi_rater_scores')
                ->selectRaw('target_user_id as user_id, AVG(score) as avg_score')
                ->where('period_id', $period->id)
                ->whereIn('target_user_id', $userIds)
                ->where('performance_criteria_id', $cid)
                ->groupBy('target_user_id')
                ->pluck('avg_score', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();

            // Prefer submitted details; otherwise use fallback scores
            $raw['kedisiplinan'] = $fromDetails + array_diff_key($fromScores, $fromDetails);
        }

        // 360: Ambil rata-rata dari modul 360 yang dipakai UI (multi_rater_scores).
        // Jika ada data header submitted (multi_rater_assessment_details), itu dipakai sebagai fallback.
        if (!empty($criteriaIds['kedisiplinan'])) {
            $raw['kedisiplinan'] = $this->collect360Avg($period->id, (int) $criteriaIds['kedisiplinan'], $userIds);
        }
        if (!empty($criteriaIds['kerjasama'])) {
            $raw['kerjasama'] = $this->collect360Avg($period->id, (int) $criteriaIds['kerjasama'], $userIds);
        }

        // Kontribusi Tambahan: SUM score approved
        $raw['kontribusi'] = DB::table('additional_contributions')
            ->selectRaw('user_id, COALESCE(SUM(score),0) as total_score')
            ->where('assessment_period_id', $period->id)
            ->where('validation_status', ContributionValidationStatus::APPROVED->value)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('total_score', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // Pasien: SUM criteria_metrics.value_numeric for "Jumlah Pasien Ditangani" criteria
        if (!empty($criteriaIds['pasien'])) {
            $pid = (int) $criteriaIds['pasien'];
            $raw['pasien'] = DB::table('criteria_metrics')
                ->selectRaw('user_id, COALESCE(SUM(value_numeric),0) as total_value')
                ->where('assessment_period_id', $period->id)
                ->where('performance_criteria_id', $pid)
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->pluck('total_value', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();
        }

            // Komplain: SUM criteria_metrics.value_numeric for "Jumlah Komplain Pasien" criteria
            if (!empty($criteriaIds['komplain'])) {
                $cid = (int) $criteriaIds['komplain'];
                $raw['komplain'] = DB::table('criteria_metrics')
                ->selectRaw('user_id, COALESCE(SUM(value_numeric),0) as total_value')
                ->where('assessment_period_id', $period->id)
                ->where('performance_criteria_id', $cid)
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->pluck('total_value', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();
            }

        // Rating: (avg_rating * rater_count) from approved reviews in this unit, decided_at within period date range
        if ($unitId && $start && $end) {
            $startDate = (string) $start;
            $endDate = (string) $end;
            $ratingRows = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->selectRaw('rd.medical_staff_id as user_id, AVG(rd.rating) as avg_rating, COUNT(*) as total_raters')
                ->where('r.status', ReviewStatus::APPROVED->value)
                ->where('r.unit_id', $unitId)
                ->whereIn('rd.medical_staff_id', $userIds)
                ->whereDate('r.decided_at', '>=', $startDate)
                ->whereDate('r.decided_at', '<=', $endDate)
                ->groupBy('rd.medical_staff_id')
                ->get();

            $tmp = [];
            foreach ($ratingRows as $row) {
                $avg = $row->avg_rating !== null ? (float) $row->avg_rating : 0.0;
                $cnt = (int) ($row->total_raters ?? 0);
                $tmp[(int)$row->user_id] = $avg * max($cnt, 0);
            }
            $raw['rating'] = $tmp;
        }

        $totals = [];
        foreach ($raw as $key => $map) {
            $sum = 0.0;
            foreach ($userIds as $uid) {
                $sum += (float) ($map[$uid] ?? 0.0);
            }
            $totals[$key] = $sum;
        }

        return [$raw, $totals];
    }

    /**
     * @param array<int> $userIds
     * @return array<int,float>
     */
    private function collect360Avg(int $periodId, int $criteriaId, array $userIds): array
    {
        // primary: scores saved from UI
        $fromScores = DB::table('multi_rater_scores')
            ->selectRaw('target_user_id as user_id, AVG(score) as avg_score')
            ->where('period_id', $periodId)
            ->where('performance_criteria_id', $criteriaId)
            ->whereIn('target_user_id', $userIds)
            ->groupBy('target_user_id')
            ->pluck('avg_score', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // fallback: submitted header details
        $fromSubmitted = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->selectRaw('mra.assessee_id as user_id, AVG(d.score) as avg_score')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id')
            ->pluck('avg_score', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // merge prefer multi_rater_scores
        $out = $fromSubmitted;
        foreach ($fromScores as $uid => $avg) {
            $out[(int) $uid] = (float) $avg;
        }
        return $out;
    }
}
