<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillPerformanceAssessmentSnapshots extends Command
{
    protected $signature = 'snapshots:backfill-performance-assessments
                            {--period_id= : Limit to a single assessment_period_id}
                            {--dry-run : Show what would be inserted without writing}';

    protected $description = 'Backfill performance assessment snapshots for frozen periods (locked/approval/closed).';

    public function handle(PerformanceScoreService $scoreSvc): int
    {
        if (!Schema::hasTable('performance_assessment_snapshots')) {
            $this->error('Table performance_assessment_snapshots does not exist. Run php artisan migrate first.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('performance_assessments') || !Schema::hasTable('users')) {
            $this->error('Required tables are missing (assessment_periods/performance_assessments/users).');
            return self::FAILURE;
        }

        $periodIdOpt = $this->option('period_id');
        $periodId = $periodIdOpt !== null && $periodIdOpt !== '' ? (int) $periodIdOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $periodsQuery = AssessmentPeriod::query()
            ->whereIn('status', [
                AssessmentPeriod::STATUS_LOCKED,
                AssessmentPeriod::STATUS_APPROVAL,
                AssessmentPeriod::STATUS_CLOSED,
            ])
            ->orderBy('start_date');

        if ($periodId !== null && $periodId > 0) {
            $periodsQuery->where('id', $periodId);
        }

        $periods = $periodsQuery->get(['id', 'name', 'status', 'start_date', 'end_date']);
        if ($periods->isEmpty()) {
            $this->info('No frozen periods found for backfill.');
            return self::SUCCESS;
        }

        $totalInserted = 0;
        $totalPeriods = 0;

        foreach ($periods as $period) {
            $totalPeriods++;
            $this->line(sprintf('Period #%d (%s) status=%s', (int) $period->id, (string) ($period->name ?? '-'), (string) $period->status));

            // Only users that already have PerformanceAssessment rows for this period.
            $periodUserIds = DB::table('performance_assessments')
                ->where('assessment_period_id', (int) $period->id)
                ->pluck('user_id')
                ->map(fn($v) => (int) $v)
                ->all();

            if (empty($periodUserIds)) {
                $this->warn('  - No performance_assessments rows; skipping.');
                continue;
            }

            // Skip users that already have snapshots.
            $existingSnapshotUserIds = DB::table('performance_assessment_snapshots')
                ->where('assessment_period_id', (int) $period->id)
                ->whereIn('user_id', $periodUserIds)
                ->pluck('user_id')
                ->map(fn($v) => (int) $v)
                ->all();

            $existingSet = array_fill_keys($existingSnapshotUserIds, true);
            $missingUserIds = array_values(array_filter($periodUserIds, fn($uid) => !isset($existingSet[(int) $uid])));

            if (empty($missingUserIds)) {
                $this->info('  - Snapshots already complete; skipping.');
                continue;
            }

            // Group missing users by unit+profession for proper scope.
            $users = User::query()
                ->whereIn('id', $missingUserIds)
                ->get(['id', 'unit_id', 'profession_id']);

            $groups = [];
            foreach ($users as $u) {
                $unitId = $u->unit_id;
                if (!$unitId) {
                    continue;
                }
                $professionId = $u->profession_id;
                $key = ((int) $unitId) . '|' . ($professionId === null ? 'null' : (string) (int) $professionId);
                $groups[$key] ??= [
                    'unit_id' => (int) $unitId,
                    'profession_id' => $professionId === null ? null : (int) $professionId,
                    'user_ids' => [],
                ];
                $groups[$key]['user_ids'][] = (int) $u->id;
            }

            $periodInserted = 0;
            foreach ($groups as $g) {
                $unitId = (int) $g['unit_id'];
                $professionId = $g['profession_id'];
                $userIds = (array) $g['user_ids'];

                if ($unitId <= 0 || empty($userIds)) {
                    continue;
                }

                $calc = $scoreSvc->calculate($unitId, $period, $userIds, $professionId);
                $criteriaIds = array_values(array_map('intval', (array) ($calc['criteria_ids'] ?? [])));

                $now = now();
                $rows = [];
                $membershipRows = [];
                foreach ($userIds as $uid) {
                    $uid = (int) $uid;
                    $userRow = $calc['users'][$uid] ?? null;
                    if (!$userRow) {
                        continue;
                    }

                    $rows[] = [
                        'assessment_period_id' => (int) $period->id,
                        'user_id' => $uid,
                        'payload' => json_encode([
                            'version' => 1,
                            'calc' => [
                                'criteria_ids' => $criteriaIds,
                                'weights' => (array) ($calc['weights'] ?? []),
                                'max_by_criteria' => (array) ($calc['max_by_criteria'] ?? []),
                                'min_by_criteria' => (array) ($calc['min_by_criteria'] ?? []),
                                'sum_raw_by_criteria' => (array) ($calc['sum_raw_by_criteria'] ?? []),
                                'basis_by_criteria' => (array) ($calc['basis_by_criteria'] ?? []),
                                'basis_value_by_criteria' => (array) ($calc['basis_value_by_criteria'] ?? []),
                                'custom_target_by_criteria' => (array) ($calc['custom_target_by_criteria'] ?? []),
                                'user' => $userRow,
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                        'snapshotted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (\Illuminate\Support\Facades\Schema::hasTable('assessment_period_user_membership_snapshots')) {
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

                if (empty($rows)) {
                    continue;
                }

                if ($dryRun) {
                    $this->info(sprintf('  - DRY RUN: would insert %d snapshots (unit=%d profession=%s)', count($rows), $unitId, $professionId === null ? 'null' : (string) $professionId));
                    $periodInserted += count($rows);
                    continue;
                }

                DB::table('performance_assessment_snapshots')->insertOrIgnore($rows);

                if (!empty($membershipRows) && \Illuminate\Support\Facades\Schema::hasTable('assessment_period_user_membership_snapshots')) {
                    DB::table('assessment_period_user_membership_snapshots')->insertOrIgnore($membershipRows);
                }

                $insertedNow = DB::table('performance_assessment_snapshots')
                    ->where('assessment_period_id', (int) $period->id)
                    ->whereIn('user_id', array_map('intval', $userIds))
                    ->count();

                // We can't directly get affected rows from insertOrIgnore across DBs reliably,
                // so we report based on attempted rows for the period summary.
                $periodInserted += count($rows);
            }

            $totalInserted += $periodInserted;
            $this->info(sprintf('  - Backfill attempted for %d missing snapshots.', $periodInserted));
        }

        $this->info(sprintf('Done. Periods processed: %d. Snapshot rows attempted: %d.%s', $totalPeriods, $totalInserted, $dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
