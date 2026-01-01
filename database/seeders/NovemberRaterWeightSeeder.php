<?php

namespace Database\Seeders;

use App\Enums\RaterWeightStatus;
use App\Models\RaterWeight;
use App\Services\RaterWeights\RaterWeightGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NovemberRaterWeightSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('unit_rater_weights')) {
            return;
        }

        $periodIds = $this->resolveTargetPeriodIds();
        if (empty($periodIds)) {
            $this->command?->warn('NovemberRaterWeightSeeder: target assessment period not found. Set env RATER_WEIGHT_PERIOD_ID to force a period.');
            return;
        }

        foreach ($periodIds as $periodId) {
            $periodId = (int) $periodId;
            if ($periodId <= 0) {
                continue;
            }

            $unitIds = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $periodId)
                ->where('status', '!=', 'archived')
                ->distinct()
                ->pluck('unit_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            if (empty($unitIds)) {
                $this->command?->warn("NovemberRaterWeightSeeder: no unit_criteria_weights found for period_id={$periodId}.");
                continue;
            }

            // Ensure rater weight rows exist.
            foreach ($unitIds as $unitId) {
                app(RaterWeightGenerator::class)->syncForUnitPeriod((int) $unitId, $periodId);
            }

            // Fill missing weights, but only when a group is entirely empty (non-destructive).
            $rows = RaterWeight::query()
                ->where('assessment_period_id', $periodId)
                ->whereIn('unit_id', $unitIds)
                ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value])
                ->orderBy('unit_id')
                ->orderBy('performance_criteria_id')
                ->orderBy('assessee_profession_id')
                ->orderBy('assessor_type')
                ->orderByRaw('COALESCE(assessor_level, 999999) ASC')
                ->orderByRaw('COALESCE(assessor_profession_id, 999999) ASC')
                ->get();

            if ($rows->isEmpty()) {
                $this->command?->info("NovemberRaterWeightSeeder: nothing to seed for period_id={$periodId}.");
                continue;
            }

            $updated = 0;

            $groups = $rows->groupBy(function ($r) {
                return (int) $r->unit_id . ':' . (int) $r->performance_criteria_id . ':' . (int) $r->assessee_profession_id;
            });

            DB::transaction(function () use ($groups, &$updated) {
                foreach ($groups as $groupRows) {
                    // If group has exactly one row, it should be auto-100.
                    if ($groupRows->count() === 1) {
                        $rw = $groupRows->first();
                        if ($rw && $rw->weight === null) {
                            $rw->weight = 100;
                            $rw->save();
                            $updated++;
                        }
                        continue;
                    }

                    // Non-destructive rule: only fill when ALL weights are still null.
                    $allNull = $groupRows->every(fn ($r) => $r->weight === null);
                    if (!$allNull) {
                        continue;
                    }

                    // Realistic default distribution (in basis points) by assessor_type.
                    // supervisor highest, peer moderate, subordinate smaller, self smallest.
                    $typePriority = [
                        'supervisor' => 6000,
                        'peer' => 2500,
                        'subordinate' => 1000,
                        'self' => 500,
                    ];

                    $rowsByType = $groupRows->groupBy(fn ($r) => (string) ($r->assessor_type ?? ''));
                    $presentTypes = array_values(array_filter(array_keys($rowsByType->all())));
                    if (empty($presentTypes)) {
                        continue;
                    }

                    $presentWeights = [];
                    $sumBase = 0;
                    foreach ($presentTypes as $t) {
                        $w = (int) ($typePriority[$t] ?? 0);
                        if ($w <= 0) {
                            continue;
                        }
                        $presentWeights[$t] = $w;
                        $sumBase += $w;
                    }

                    // Fallback: if types unknown, evenly distribute.
                    if ($sumBase <= 0) {
                        $n = $groupRows->count();
                        if ($n <= 0) {
                            continue;
                        }
                        $baseCents = intdiv(10000, $n);
                        $remainder = 10000 - ($baseCents * $n);
                        $i = 0;
                        foreach ($groupRows as $rw) {
                            $cents = $baseCents + ($i === 0 ? $remainder : 0);
                            $rw->weight = $cents / 100;
                            $rw->save();
                            $updated++;
                            $i++;
                        }
                        continue;
                    }

                    // Normalize type shares to sum to 10000 basis points.
                    $typeShares = [];
                    $allocated = 0;
                    foreach ($presentWeights as $t => $w) {
                        $share = (int) floor(($w / $sumBase) * 10000);
                        $typeShares[$t] = $share;
                        $allocated += $share;
                    }
                    $remainder = 10000 - $allocated;
                    if ($remainder !== 0) {
                        $target = array_key_exists('supervisor', $typeShares) ? 'supervisor' : array_key_first($typeShares);
                        $typeShares[$target] = (int) ($typeShares[$target] ?? 0) + $remainder;
                    }

                    // Distribute each type share across its rows.
                    foreach ($typeShares as $type => $shareBp) {
                        $typeRows = $rowsByType->get($type, collect());
                        if ($typeRows->isEmpty()) {
                            continue;
                        }

                        // Supervisor: bias level 1 bigger than level 2, etc.
                        if ($type === 'supervisor' && $typeRows->count() > 1) {
                            $sorted = $typeRows
                                ->sortBy(fn ($r) => $r->assessor_level === null ? 999999 : (int) $r->assessor_level)
                                ->values();

                            $ratios = [0.60, 0.25, 0.10, 0.05];
                            while (count($ratios) < $sorted->count()) {
                                $ratios[] = 0.01;
                            }
                            $ratios = array_slice($ratios, 0, $sorted->count());
                            $ratioSum = array_sum($ratios);
                            if ($ratioSum <= 0) {
                                $ratioSum = 1;
                            }

                            $bps = [];
                            $alloc = 0;
                            for ($i = 0; $i < $sorted->count(); $i++) {
                                $bp = (int) floor(($ratios[$i] / $ratioSum) * $shareBp);
                                $bps[$i] = $bp;
                                $alloc += $bp;
                            }
                            $rem = $shareBp - $alloc;
                            if ($rem !== 0) {
                                $bps[0] = ($bps[0] ?? 0) + $rem;
                            }

                            for ($i = 0; $i < $sorted->count(); $i++) {
                                $rw = $sorted[$i];
                                $rw->weight = ((int) $bps[$i]) / 100;
                                $rw->save();
                                $updated++;
                            }
                            continue;
                        }

                        // Default: split evenly within the same assessor_type.
                        $n = $typeRows->count();
                        $base = intdiv($shareBp, $n);
                        $rem = $shareBp - ($base * $n);
                        $i = 0;
                        foreach ($typeRows as $rw) {
                            $bp = $base + ($i === 0 ? $rem : 0);
                            $rw->weight = $bp / 100;
                            $rw->save();
                            $updated++;
                            $i++;
                        }
                    }
                }
            });

            $this->command?->info("NovemberRaterWeightSeeder: period_id={$periodId} updated_rows={$updated}");
        }
    }

    /**
     * @return array<int>
     */
    private function resolveTargetPeriodIds(): array
    {
        $forced = (int) (env('RATER_WEIGHT_PERIOD_ID') ?: 0);
        if ($forced > 0) {
            return [$forced];
        }

        // Prefer the demo period name if it exists.
        $demoId = (int) (DB::table('assessment_periods')->where('name', 'November 2025')->value('id') ?? 0);
        if ($demoId > 0) {
            return [$demoId];
        }

        // Default: pick the latest period that starts in November.
        $ids = DB::table('assessment_periods')
            ->whereMonth('start_date', 11)
            ->orderByDesc('start_date')
            ->limit(1)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (!empty($ids)) {
            return $ids;
        }

        // Fallback: name-based search.
        return DB::table('assessment_periods')
            ->where('name', 'like', '%Nov%')
            ->orWhere('name', 'like', '%November%')
            ->orderByDesc('start_date')
            ->limit(1)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();
    }
}
