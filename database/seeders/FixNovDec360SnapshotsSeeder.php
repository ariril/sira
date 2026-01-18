<?php

namespace Database\Seeders;

use App\Models\AssessmentPeriod;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixNovDec360SnapshotsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('performance_assessment_snapshots')) {
            $this->command?->warn('Skipping FixNovDec360SnapshotsSeeder: required tables not found.');
            return;
        }

        $periodIds = array_values(array_filter([
            $this->resolvePeriodIdByNameOrMonth(['November 2025', 'Nov 2025', 'Novembre 2025'], 2025, 11),
            $this->resolvePeriodIdByNameOrMonth(['December 2025', 'Desember 2025', 'Dec 2025', 'Des 2025'], 2025, 12),
        ], fn ($v) => (int) $v > 0));

        if (empty($periodIds)) {
            $this->command?->warn('FixNovDec360SnapshotsSeeder: no target periods found (Nov/Dec 2025).');
            return;
        }

        /** @var PeriodPerformanceAssessmentService $svc */
        $svc = app(PeriodPerformanceAssessmentService::class);

        foreach ($periodIds as $pid) {
            $pid = (int) $pid;
            $period = AssessmentPeriod::query()->find($pid);
            if (!$period) {
                continue;
            }

            $this->command?->info(sprintf('Rebuilding snapshots for period #%d (%s) status=%s', (int) $period->id, (string) ($period->name ?? '-'), (string) ($period->status ?? '-')));

            // Delete old snapshots so the service can regenerate fresh payloads.
            DB::table('performance_assessment_snapshots')
                ->where('assessment_period_id', $pid)
                ->delete();

            if (Schema::hasTable('assessment_period_user_membership_snapshots')) {
                DB::table('assessment_period_user_membership_snapshots')
                    ->where('assessment_period_id', $pid)
                    ->delete();
            }

            // Recalculate and (if frozen) recreate snapshots.
            $svc->recalculateForPeriodId($pid);

            $newCount = (int) DB::table('performance_assessment_snapshots')
                ->where('assessment_period_id', $pid)
                ->count();

            $this->command?->info('Snapshot rows now: ' . $newCount);
        }
    }

    private function resolvePeriodIdByNameOrMonth(array $names, int $year, int $month): int
    {
        $names = array_values(array_filter(array_map('strval', $names), fn ($v) => $v !== ''));

        if (!empty($names)) {
            $id = (int) (DB::table('assessment_periods')
                ->whereIn('name', $names)
                ->orderByDesc('start_date')
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return (int) (DB::table('assessment_periods')
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->orderByDesc('start_date')
            ->value('id') ?? 0);
    }
}
