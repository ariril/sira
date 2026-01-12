<?php

namespace App\Services\AssessmentPeriods;

use App\Enums\AttendanceStatus;
use App\Enums\ReviewStatus;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\User;
use App\Services\CriteriaEngine\CriteriaRegistry;
use App\Services\MultiRater\RaterWeightResolver;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeriodPerformanceAssessmentService
{
    public function __construct(
        private readonly PerformanceScoreService $scoreSvc,
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

    /**
     * Recalculate for a specific unit + profession (optionally limited to specific user_ids).
    * Uses CriteriaEngine-backed scoring via PerformanceScoreService (NOT legacy BestScenarioCalculator).
     *
     * @param array<int>|null $userIds
     */
    public function recalculateForGroup(int $periodId, ?int $unitId, ?int $professionId, ?array $userIds = null): void
    {
        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            return;
        }

        $users = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->when($professionId, fn($q) => $q->where('profession_id', $professionId))
            ->when(is_array($userIds) && !empty($userIds), fn($q) => $q->whereIn('id', array_values(array_unique(array_map('intval', $userIds)))))
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
                        'total_wsm_value_score' => 0,
                        'validation_status' => 'Menunggu Validasi',
                        'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                    ]
                );
            }

            // Map assessment ids for fast upsert
            $assessmentMap = PerformanceAssessment::query()
                ->where('assessment_period_id', $period->id)
                ->whereIn('user_id', $userIds)
                ->pluck('id', 'user_id');

            // Calculate using the CriteriaEngine-backed score service (normalization_basis + COST).
            // This service is also responsible for applying weight status rules (active vs draft/archived)
            // and for exposing all configured criteria so they can still be displayed.
            $calc = $this->scoreSvc->calculate((int) $unitId, $period, $userIds, $professionId);
            $criteriaIds = array_values(array_map('intval', (array) ($calc['criteria_ids'] ?? [])));

            // If the period is frozen, persist a snapshot so later UI reads remain stable
            // even if admins change performance_criterias.normalization_basis.
            if ($period->isFrozen() && Schema::hasTable('performance_assessment_snapshots')) {
                $now = now();
                $snapRows = [];
                $membershipRows = [];

                foreach ($userIds as $uid) {
                    $uid = (int) $uid;
                    $userRow = $calc['users'][$uid] ?? null;
                    if (!$userRow) {
                        continue;
                    }

                    $snapRows[] = [
                        'assessment_period_id' => (int) $period->id,
                        'user_id' => $uid,
                        'payload' => json_encode([
                            'version' => 1,
                            'calc' => [
                                // Keep a calculate()-compatible shape.
                                'criteria_ids' => $criteriaIds,
                                'weights' => (array) ($calc['weights'] ?? []),
                                'max_by_criteria' => (array) ($calc['max_by_criteria'] ?? []),
                                'min_by_criteria' => (array) ($calc['min_by_criteria'] ?? []),
                                'sum_raw_by_criteria' => (array) ($calc['sum_raw_by_criteria'] ?? []),
                                'basis_by_criteria' => (array) ($calc['basis_by_criteria'] ?? []),
                                'basis_value_by_criteria' => (array) ($calc['basis_value_by_criteria'] ?? []),
                                'custom_target_by_criteria' => (array) ($calc['custom_target_by_criteria'] ?? []),
                                // Explicit totals to make downstream consumers (e.g., remuneration) consistent.
                                'total_wsm_relative' => $userRow['total_wsm_relative'] ?? ($userRow['total_wsm'] ?? null),
                                'total_wsm_value' => $userRow['total_wsm_value'] ?? null,
                                'user' => $userRow,
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                        'snapshotted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (Schema::hasTable('assessment_period_user_membership_snapshots')) {
                        $membershipRows[] = [
                            'assessment_period_id' => (int) $period->id,
                            'user_id' => $uid,
                            'unit_id' => (int) $unitId,
                            'profession_id' => $professionId === null ? null : (int) $professionId,
                            'snapshotted_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($snapRows)) {
                    // Insert-only for stability (don't overwrite existing snapshots).
                    DB::table('performance_assessment_snapshots')->insertOrIgnore($snapRows);
                }

                if (!empty($membershipRows) && Schema::hasTable('assessment_period_user_membership_snapshots')) {
                    DB::table('assessment_period_user_membership_snapshots')->insertOrIgnore($membershipRows);
                }
            }

            foreach ($userIds as $uid) {
                $assessmentId = (int) ($assessmentMap[$uid] ?? 0);
                if (!$assessmentId) {
                    continue;
                }

                $userRow = $calc['users'][(int) $uid] ?? null;
                $totalWsmRelative = $userRow ? ($userRow['total_wsm_relative'] ?? ($userRow['total_wsm'] ?? null)) : null;
                $totalWsmValue = $userRow ? ($userRow['total_wsm_value'] ?? null) : null;

                PerformanceAssessment::query()
                    ->where('id', $assessmentId)
                    ->update([
                        'assessment_date' => $period->end_date ?? now()->toDateString(),
                        // Keep legacy column as RELATIVE score for TOTAL_UNIT + ranking/UI.
                        'total_wsm_score' => $totalWsmRelative === null ? null : round((float) $totalWsmRelative, 2),
                        // New VALUE score for ATTACHED payout% (Excel v5).
                        'total_wsm_value_score' => $totalWsmValue === null ? null : round((float) $totalWsmValue, 2),
                        'supervisor_comment' => ($totalWsmRelative === null)
                            ? 'Belum ada bobot kriteria AKTIF untuk unit ini pada periode ini.'
                            : 'Dihitung otomatis dari data tabel.',
                    ]);

                // Remove old details for criteria that are no longer configured for this unit+period
                if (!empty($criteriaIds)) {
                    PerformanceAssessmentDetail::query()
                        ->where('performance_assessment_id', $assessmentId)
                        ->whereNotIn('performance_criteria_id', $criteriaIds)
                        ->delete();
                }

                $criteriaRows = $userRow['criteria'] ?? [];
                foreach ($criteriaRows as $row) {
                    $criteriaId = (int) ($row['criteria_id'] ?? 0);
                    if ($criteriaId <= 0) {
                        continue;
                    }

                    // Per definisi: detail.score menyimpan nilai_relatif (0–100).
                    $score = round((float) ($row['nilai_relativ_unit'] ?? 0.0), 2);
                    $rawValue = (float) ($row['raw'] ?? 0.0);
                    $nilaiNormalisasi = (float) ($row['nilai_normalisasi'] ?? 0.0);
                    PerformanceAssessmentDetail::updateOrCreate(
                        [
                            'performance_assessment_id' => $assessmentId,
                            'performance_criteria_id' => $criteriaId,
                        ],
                        [
                            'criteria_metric_id' => null,
                            'score' => $score,
                            'meta' => [
                                'raw_value' => $rawValue,
                                'nilai_normalisasi' => $nilaiNormalisasi,
                            ],
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
        * - Only status=active is considered.
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

        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', (int) $unitId)
            ->where('assessment_period_id', $periodId)
            ->when($isActive, function ($q) {
                $q->where('status', 'active');
            }, function ($q) {
                $q->where(function ($q) {
                    $q->where('status', 'active')
                        ->orWhere(function ($q) {
                            $q->where('status', 'archived');
                            if (\Illuminate\Support\Facades\Schema::hasColumn('unit_criteria_weights', 'was_active_before')) {
                                $q->where('was_active_before', 1);
                            }
                        });
                });
            })
            ->orderByRaw("CASE WHEN status='active' THEN 0 WHEN status='archived' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get(['performance_criteria_id', 'weight', 'status', 'was_active_before', 'updated_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $cid = (int) ($r->performance_criteria_id ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!array_key_exists($cid, $out)) {
                $out[$cid] = (float) $r->weight;
            }
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

        // Attendance-derived metrics (count workdays present + holidays)
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
                ->whereIn('attendance_status', [
                    AttendanceStatus::HADIR->value,
                    AttendanceStatus::TERLAMBAT->value,
                    AttendanceStatus::LIBUR_UMUM->value,
                    AttendanceStatus::LIBUR_RUTIN->value,
                ])
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
            $raw['kedisiplinan'] = $this->collect360Avg($period->id, (int) $criteriaIds['kedisiplinan'], $userIds, $unitId);
        }
        if (!empty($criteriaIds['kerjasama'])) {
            $raw['kerjasama'] = $this->collect360Avg($period->id, (int) $criteriaIds['kerjasama'], $userIds, $unitId);
        }

        // Tugas Tambahan (kontribusi):
        // - task-based: dari additional_task_claims (approved/completed) dengan snapshot awarded_points
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

        $tmp = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $tmp[$uid] = (float)($claimPoints[$uid] ?? 0);
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
    private function collect360Avg(int $periodId, int $criteriaId, array $userIds, ?int $unitId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $criteriaId = (int) $criteriaId;
        $periodId = (int) $periodId;
        $unitId = $unitId === null ? null : (int) $unitId;

        if (empty($userIds) || $criteriaId <= 0 || $periodId <= 0) {
            return [];
        }

        // AVG per assessee + assessor_type
        $avgRows = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->selectRaw('mra.assessee_id as user_id, mra.assessor_type as assessor_type, AVG(d.score) as avg_score')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id', 'mra.assessor_type')
            ->get();

        $avgMap = [];
        foreach ($avgRows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            $type = (string) ($row->assessor_type ?? '');
            if ($uid <= 0 || $type === '') {
                continue;
            }
            $avgMap[$uid][$type] = (float) ($row->avg_score ?? 0.0);
        }

        // Resolve assessee profession_id to choose the correct weight set.
        $professionByUserId = DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('profession_id', 'id')
            ->map(fn($v) => $v === null ? null : (int) $v)
            ->all();

        $professionIds = [];
        foreach ($professionByUserId as $pid) {
            if (is_int($pid) && $pid > 0) {
                $professionIds[$pid] = true;
            }
        }
        $professionIds = array_keys($professionIds);

        $resolvedWeightsByProfession = [];
        if ($unitId !== null && $unitId > 0) {
            $resolvedWeightsByProfession = RaterWeightResolver::resolveForCriteria($periodId, $unitId, $criteriaId, $professionIds);
        }
        $defaults = RaterWeightResolver::defaults();

        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $avgs = $avgMap[$uid] ?? [];
            if (empty($avgs)) {
                $out[$uid] = 0.0;
                continue;
            }

            $professionId = $professionByUserId[$uid] ?? null;
            $weights = $defaults;
            if (is_int($professionId) && $professionId > 0 && isset($resolvedWeightsByProfession[$professionId])) {
                $weights = array_merge($weights, (array) $resolvedWeightsByProfession[$professionId]);
            }

            $weightedSum = 0.0;
            $weightSum = 0.0;
            foreach (['self', 'supervisor', 'peer', 'subordinate'] as $assessorType) {
                if (!array_key_exists($assessorType, $avgs)) {
                    continue;
                }
                $avg = (float) ($avgs[$assessorType] ?? 0.0);
                $w = (float) ($weights[$assessorType] ?? 0.0);
                if ($w <= 0.0) {
                    continue;
                }
                $weightedSum += $avg * ($w / 100.0);
                $weightSum += $w;
            }

            $out[$uid] = $weightSum > 0.0 ? (float) ($weightedSum / ($weightSum / 100.0)) : 0.0;
        }

        return $out;
    }
}
