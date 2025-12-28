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
use App\Services\CriteriaEngine\CriteriaRegistry;
use App\Services\CriteriaEngine\PerformanceScoreService as CriteriaEnginePerformanceScoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeriodPerformanceAssessmentService
{
    public function __construct(
        private readonly CriteriaEnginePerformanceScoreService $engine,
        private readonly CriteriaRegistry $registry,
    ) {
    }
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
            $this->recalculateGroup($period, (array) $g['user_ids'], $g['unit_id'], $g['profession_id']);
        }
    }

    /**
     * @param array<int> $userIds
     */
    private function recalculateGroup(AssessmentPeriod $period, array $userIds, ?int $unitId, ?int $professionId): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        if (!$unitId) {
            return;
        }

        // Ensure PerformanceAssessment exists for each user in this period
        DB::transaction(function () use ($period, $userIds, $unitId, $professionId) {
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

            // Active criteria MUST come from configuration (unit_criteria_weights)
            $weightByCriteriaId = $this->resolveWeightByCriteriaId($period, $unitId);
            $activeCriteriaIds = array_values(array_map('intval', array_keys($weightByCriteriaId)));

            // Map assessment ids for fast upsert
            $assessmentMap = PerformanceAssessment::query()
                ->where('assessment_period_id', $period->id)
                ->whereIn('user_id', $userIds)
                ->pluck('id', 'user_id');

            if (empty($activeCriteriaIds)) {
                // No configured criteria -> store total_wsm_score as null (not available)
                foreach ($userIds as $uid) {
                    $assessmentId = (int) ($assessmentMap[$uid] ?? 0);
                    if ($assessmentId <= 0) continue;

                    PerformanceAssessment::query()
                        ->where('id', $assessmentId)
                        ->update([
                            'assessment_date' => $period->end_date ?? now()->toDateString(),
                            'total_wsm_score' => null,
                            'supervisor_comment' => 'Belum ada konfigurasi bobot kriteria untuk unit ini.',
                        ]);
                }
                return;
            }

            // Build key => criteria_id map for active criteria
            $cols = ['id', 'name', 'input_method', 'is_360', 'type'];
            if (Schema::hasColumn('performance_criterias', 'source')) {
                $cols[] = 'source';
            }

            $criteriaRows = PerformanceCriteria::query()
                ->whereIn('id', $activeCriteriaIds)
                ->get($cols);

            $criteriaIdByKey = [];
            foreach ($criteriaRows as $c) {
                $key = $this->registry->keyForCriteria($c);
                if ($key) {
                    $criteriaIdByKey[$key] = (int) $c->id;
                }
            }

            $calc = $this->engine->calculate((int) $unitId, $period, $userIds, $professionId);

            foreach ($userIds as $uid) {
                $assessmentId = (int) ($assessmentMap[$uid] ?? 0);
                if (!$assessmentId) {
                    continue;
                }

                $userRow = $calc['users'][(int) $uid] ?? null;
                $totalWsm = $userRow ? (float) ($userRow['total_wsm'] ?? 0.0) : 0.0;

                PerformanceAssessment::query()
                    ->where('id', $assessmentId)
                    ->update([
                        'assessment_date' => $period->end_date ?? now()->toDateString(),
                        'total_wsm_score' => round($totalWsm, 2),
                        'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                    ]);

                // Remove old details for criteria that are no longer active
                PerformanceAssessmentDetail::query()
                    ->where('performance_assessment_id', $assessmentId)
                    ->whereNotIn('performance_criteria_id', $activeCriteriaIds)
                    ->delete();

                $criteriaRows = $userRow['criteria'] ?? [];
                foreach ($criteriaRows as $row) {
                    $key = (string) ($row['key'] ?? '');
                    $criteriaId = (int) ($criteriaIdByKey[$key] ?? 0);
                    if ($criteriaId <= 0) {
                        continue;
                    }

                    $score = round((float) ($row['normalized'] ?? 0.0), 2);
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
     * Resolve weight mapping (performance_criteria_id => weight) for a unit and period.
     *
     * Rules:
     * - Active period: only status=active is considered.
     * - Non-active period: prefer status=active, fallback to status=archived (for historical periods).
     *
     * @return array<int,float>
     */
    private function resolveWeightByCriteriaId(AssessmentPeriod $period, ?int $unitId): array
    {
        if (!$unitId) {
            return [];
        }

        $periodId = (int) $period->id;
        $isActive = (string) ($period->status ?? '') === AssessmentPeriod::STATUS_ACTIVE;
        $statuses = $isActive ? ['active'] : ['active', 'archived'];

        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', (int) $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', $statuses)
            ->get(['performance_criteria_id', 'weight', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        // For non-active periods, prefer active rows if any exist.
        if (!$isActive && $rows->contains(fn($r) => (string) $r->status === 'active')) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'active');
        } elseif (!$isActive) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'archived');
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->performance_criteria_id] = (float) $r->weight;
        }

        return $out;
    }

    /**
     * @param array{kehadiran:?int,jam_kerja:?int,lembur:?int,keterlambatan:?int,kedisiplinan:?int,kerjasama:?int,kontribusi:?int,pasien:?int,komplain:?int,rating:?int} $criteriaIds
     * @param array<int> $userIds
     * @return array{0: array<string, array<int, float>>, 1: array<string, float>}
     */
    private function collectRawAndDenominators(AssessmentPeriod $period, array $criteriaIds, array $userIds, ?int $unitId): array
    {
        $start = $period->start_date;
        $end = $period->end_date;

        $raw = [
            'kehadiran' => [],
            'jam_kerja' => [],
            'lembur' => [],
            'keterlambatan' => [],
            'kedisiplinan' => [],
            'kerjasama' => [],
            'kontribusi' => [],
            'pasien' => [],
            'komplain' => [],
            'rating' => [],
        ];

        // Attendance-derived metrics (Hadir only)
        if (($criteriaIds['kehadiran'] ?? null) || ($criteriaIds['jam_kerja'] ?? null) || ($criteriaIds['lembur'] ?? null) || ($criteriaIds['keterlambatan'] ?? null)) {
            $attendanceRows = DB::table('attendances')
                ->selectRaw(
                    'user_id,
                     COUNT(*) as hadir_days,
                     COALESCE(SUM(work_duration_minutes),0) as work_minutes,
                     COALESCE(SUM(late_minutes),0) as late_minutes,
                     COALESCE(SUM(CASE WHEN (overtime_shift = 1 OR overtime_end IS NOT NULL OR overtime_note IS NOT NULL) THEN 1 ELSE 0 END),0) as overtime_count'
                )
                ->whereIn('user_id', $userIds)
                ->where('attendance_status', AttendanceStatus::HADIR->value)
                ->when($start && $end, fn($q) => $q->whereBetween('attendance_date', [$start, $end]))
                ->groupBy('user_id')
                ->get();

            $byUser = [];
            foreach ($attendanceRows as $r) {
                $byUser[(int)$r->user_id] = [
                    'hadir_days' => (float) ($r->hadir_days ?? 0),
                    'work_minutes' => (float) ($r->work_minutes ?? 0),
                    'late_minutes' => (float) ($r->late_minutes ?? 0),
                    'overtime_count' => (float) ($r->overtime_count ?? 0),
                ];
            }

            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                $row = $byUser[$uid] ?? ['hadir_days' => 0.0, 'work_minutes' => 0.0, 'late_minutes' => 0.0, 'overtime_count' => 0.0];
                $raw['kehadiran'][$uid] = (float) $row['hadir_days'];
                $raw['jam_kerja'][$uid] = (float) $row['work_minutes'];
                $raw['keterlambatan'][$uid] = (float) $row['late_minutes'];
                $raw['lembur'][$uid] = (float) $row['overtime_count'];
            }
        }

        // 360: Ambil rata-rata dari multi_rater_assessment_details (status submitted).
        if (!empty($criteriaIds['kedisiplinan'])) {
            $raw['kedisiplinan'] = $this->collect360Avg($period->id, (int) $criteriaIds['kedisiplinan'], $userIds);
        }
        if (!empty($criteriaIds['kerjasama'])) {
            $raw['kerjasama'] = $this->collect360Avg($period->id, (int) $criteriaIds['kerjasama'], $userIds);
        }

        // Kontribusi Tambahan:
        // - task-based: dari additional_task_claims (approved/completed) dengan snapshot awarded_points
        // - ad-hoc: dari additional_contributions approved, yang tidak ditautkan ke claim (claim_id null)
        $claimPoints = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->selectRaw('c.user_id, COALESCE(SUM(COALESCE(c.awarded_points, t.points, 0)),0) as total_score')
            ->where('t.assessment_period_id', $period->id)
            ->whereIn('c.status', ['approved', 'completed'])
            ->whereIn('c.user_id', $userIds)
            ->groupBy('c.user_id')
            ->pluck('total_score', 'c.user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $adhocPoints = DB::table('additional_contributions')
            ->selectRaw('user_id, COALESCE(SUM(score),0) as total_score')
            ->where('assessment_period_id', $period->id)
            ->where('validation_status', ContributionValidationStatus::APPROVED->value)
            ->whereNull('claim_id')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('total_score', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $tmp = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $tmp[$uid] = (float)($claimPoints[$uid] ?? 0) + (float)($adhocPoints[$uid] ?? 0);
        }
        $raw['kontribusi'] = $tmp;

        // Pasien: SUM imported_criteria_values.value_numeric for "Jumlah Pasien Ditangani" criteria
        if (!empty($criteriaIds['pasien'])) {
            $pid = (int) $criteriaIds['pasien'];
            $raw['pasien'] = DB::table('imported_criteria_values')
                ->selectRaw('user_id, COALESCE(SUM(value_numeric),0) as total_value')
                ->where('assessment_period_id', $period->id)
                ->where('performance_criteria_id', $pid)
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->pluck('total_value', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();
        }

        // Komplain: SUM imported_criteria_values.value_numeric for "Jumlah Komplain Pasien" criteria
        if (!empty($criteriaIds['komplain'])) {
            $cid = (int) $criteriaIds['komplain'];
            $raw['komplain'] = DB::table('imported_criteria_values')
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

        // Denominators:
        // - legacy sum-based keys: sum within group
        // - Kehadiran: total days in the period
        // - Jam Kerja/Lembur/Keterlambatan: max within group (unit+profession+period)
        $denominators = [];

        $sumKeys = ['kedisiplinan', 'kerjasama', 'kontribusi', 'pasien', 'komplain', 'rating', 'jam_kerja', 'lembur'];
        foreach ($sumKeys as $key) {
            $sum = 0.0;
            foreach ($userIds as $uid) {
                $sum += (float) (($raw[$key][$uid] ?? 0.0));
            }
            $denominators[$key] = $sum;
        }

        // Total days inclusive (if dates exist)
        $totalDays = 0.0;
        if ($start && $end) {
            try {
                $s = \Illuminate\Support\Carbon::parse((string) $start)->startOfDay();
                $e = \Illuminate\Support\Carbon::parse((string) $end)->startOfDay();
                $totalDays = (float) ($s->diffInDays($e) + 1);
            } catch (\Throwable $t) {
                $totalDays = 0.0;
            }
        }
        $denominators['kehadiran'] = $totalDays;

        // Keterlambatan uses MAX (cost normalization formula)
        $denominators['keterlambatan'] = 0.0;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $denominators['keterlambatan'] = max($denominators['keterlambatan'], (float) ($raw['keterlambatan'][$uid] ?? 0.0));
        }

        return [$raw, $denominators];
    }

    /**
     * @param array<int> $userIds
     * @return array<int,float>
     */
    private function collect360Avg(int $periodId, int $criteriaId, array $userIds): array
    {
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

        return $fromSubmitted;
    }
}
