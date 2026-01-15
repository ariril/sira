<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivateUnitCriteriaWeightsForPeriod extends Command
{
    protected $signature = 'weights:activate-unit-criteria-weights
                            {period_id : assessment_period_id}
                            {--unit_id= : Limit to one unit_id}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Promote unit_criteria_weights to status=active for a period (per unit+criteria), when no active weight exists yet.';

    public function handle(): int
    {
        $periodId = (int) $this->argument('period_id');
        $unitIdOpt = $this->option('unit_id');
        $unitId = $unitIdOpt !== null && $unitIdOpt !== '' ? (int) $unitIdOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($periodId <= 0) {
            $this->error('period_id must be a positive integer.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('unit_criteria_weights')) {
            $this->error('Table unit_criteria_weights does not exist.');
            return self::FAILURE;
        }

        $q = DB::table('unit_criteria_weights')
            ->where('assessment_period_id', $periodId);
        if ($unitId !== null && $unitId > 0) {
            $q->where('unit_id', $unitId);
        }

        $rows = $q->get(['id', 'unit_id', 'performance_criteria_id', 'status']);
        if ($rows->isEmpty()) {
            $this->info('No unit_criteria_weights found for this scope.');
            return self::SUCCESS;
        }

        // Group rows by unit+criteria.
        $groups = [];
        foreach ($rows as $r) {
            $u = (int) $r->unit_id;
            $c = (int) $r->performance_criteria_id;
            if ($u <= 0 || $c <= 0) {
                continue;
            }
            $key = $u . '|' . $c;
            $groups[$key][] = [
                'id' => (int) $r->id,
                'unit_id' => $u,
                'criteria_id' => $c,
                'status' => (string) $r->status,
            ];
        }

        // Promotion priority when there is no active weight yet.
        $priority = [
            'pending' => 4,
            'draft' => 3,
            'archived' => 2,
            'rejected' => 1,
        ];

        $toActivateIds = [];
        foreach ($groups as $key => $groupRows) {
            $hasActive = false;
            foreach ($groupRows as $gr) {
                if ($gr['status'] === 'active') {
                    $hasActive = true;
                    break;
                }
            }
            if ($hasActive) {
                continue;
            }

            $best = null;
            $bestP = -1;
            foreach ($groupRows as $gr) {
                $st = $gr['status'];
                if (!isset($priority[$st])) {
                    continue;
                }
                $p = (int) $priority[$st];
                if ($p > $bestP) {
                    $bestP = $p;
                    $best = $gr;
                }
            }

            if ($best && $best['id'] > 0) {
                $toActivateIds[] = (int) $best['id'];
            }
        }

        if (empty($toActivateIds)) {
            $this->info('Nothing to promote (all criteria already have an active weight).');
            return self::SUCCESS;
        }

        $this->info('Rows to promote to active: ' . count($toActivateIds));
        if ($dryRun) {
            $this->warn('DRY RUN: no changes written.');
            return self::SUCCESS;
        }

        $updated = (int) DB::table('unit_criteria_weights')
            ->whereIn('id', $toActivateIds)
            ->update(['status' => 'active', 'updated_at' => now()]);

        $this->info('Updated rows: ' . $updated);
        return self::SUCCESS;
    }
}
