<?php

namespace Database\Seeders;

use App\Enums\RaterWeightStatus;
use App\Models\AssessmentPeriod;
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

            $period = DB::table('assessment_periods')->where('id', $periodId)->first(['id', 'status', 'name']);
            if (!$period) {
                $this->command?->warn("NovemberRaterWeightSeeder: period_id={$periodId} not found.");
                continue;
            }

            // For history periods (non-active), we still seed weights so the Riwayat table is meaningful.
            // We will archive rater weights after seeding when the period is non-active.

            $unitIds = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $periodId)
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
            // Treat "all zero" as empty too (weights should not be 0 in realistic distributions).
            $rows = RaterWeight::query()
                ->where('assessment_period_id', $periodId)
                ->whereIn('unit_id', $unitIds)
                ->orderBy('unit_id')
                ->orderBy('performance_criteria_id')
                ->orderBy('assessee_profession_id')
                ->orderBy('assessor_type')
                ->orderByRaw('COALESCE(assessor_level, 999999) ASC')
                ->orderByRaw('COALESCE(assessor_profession_id, 999999) ASC')
                ->get();

            if ($rows->isEmpty()) {
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
                        if ($rw && ($rw->weight === null || (float) $rw->weight === 0.0)) {
                            $rw->weight = 100;
                            $rw->save();
                            $updated++;
                        }
                        continue;
                    }

                    // Non-destructive rule: only fill when ALL weights are still null.
                    $allNull = $groupRows->every(fn ($r) => $r->weight === null);
                    $allZero = $groupRows->every(fn ($r) => $r->weight !== null && (float) $r->weight === 0.0);
                    if (!($allNull || $allZero)) {
                        continue;
                    }

                    // Realistic default distribution (integer percent points) by assessor_type.
                    // supervisor highest, peer moderate, subordinate smaller, self smallest.
                    $typePriority = [
                        'supervisor' => 60,
                        'peer' => 25,
                        'subordinate' => 10,
                        'self' => 5,
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
                        $base = intdiv(100, $n);
                        $remainder = 100 - ($base * $n);
                        $i = 0;
                        foreach ($groupRows as $rw) {
                            $points = $base + ($i < $remainder ? 1 : 0);
                            $rw->weight = $points;
                            $rw->save();
                            $updated++;
                            $i++;
                        }
                        continue;
                    }

                    // Normalize type shares to sum to 100 integer points.
                    $typeShares = [];
                    $allocated = 0;
                    foreach ($presentWeights as $t => $w) {
                        $share = (int) floor(($w / $sumBase) * 100);
                        $typeShares[$t] = $share;
                        $allocated += $share;
                    }
                    $remainder = 100 - $allocated;
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
                                $points = (int) floor(($ratios[$i] / $ratioSum) * $shareBp);
                                $bps[$i] = $points;
                                $alloc += $points;
                            }
                            $rem = $shareBp - $alloc;
                            // Spread remainder as +1 across earliest rows to keep integers.
                            for ($k = 0; $k < abs($rem); $k++) {
                                $idx = $k % max(1, $sorted->count());
                                $bps[$idx] = ($bps[$idx] ?? 0) + ($rem > 0 ? 1 : -1);
                            }

                            for ($i = 0; $i < $sorted->count(); $i++) {
                                $rw = $sorted[$i];
                                $rw->weight = (int) ($bps[$i] ?? 0);
                                $rw->save();
                                $updated++;
                            }
                            continue;
                        }

                        // Default: split evenly within the same assessor_type.
                        $n = $typeRows->count();
                        $base = intdiv((int) $shareBp, $n);
                        $rem = (int) $shareBp - ($base * $n);
                        $i = 0;
                        foreach ($typeRows as $rw) {
                            $points = $base + ($i < $rem ? 1 : 0);
                            $rw->weight = $points;
                            $rw->save();
                            $updated++;
                            $i++;
                        }
                    }
                }
            });

            // If the target period is not active, archive rater weights after seeding so they show as history.
            if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_ACTIVE) {
                $archived = RaterWeightStatus::ARCHIVED->value;

                $rwArchived = (int) DB::table('unit_rater_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('status', '!=', $archived)
                    ->update([
                        'status' => $archived,
                        'updated_at' => now(),
                    ]);

                $pname = (string) ($period->name ?? '');
            }
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

        // Prefer explicit demo period names if they exist (Indonesian/English).
        $byName = DB::table('assessment_periods')
            ->whereIn('name', ['November 2025', 'Desember 2025', 'December 2025'])
            ->orderByDesc('start_date')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (!empty($byName)) {
            return array_values(array_unique($byName));
        }

        // Default: pick the latest period that starts in November and the latest that starts in December.
        $nov = (int) (DB::table('assessment_periods')->whereMonth('start_date', 11)->orderByDesc('start_date')->value('id') ?? 0);
        $dec = (int) (DB::table('assessment_periods')->whereMonth('start_date', 12)->orderByDesc('start_date')->value('id') ?? 0);

        $ids = array_values(array_unique(array_filter([$nov, $dec], fn ($v) => (int) $v > 0)));
        if (!empty($ids)) {
            return $ids;
        }

        // Fallback: name-based search for both months.
        $fallback = DB::table('assessment_periods')
            ->where('name', 'like', '%Nov%')
            ->orWhere('name', 'like', '%November%')
            ->orWhere('name', 'like', '%Des%')
            ->orWhere('name', 'like', '%Dec%')
            ->orWhere('name', 'like', '%Desember%')
            ->orWhere('name', 'like', '%December%')
            ->orderByDesc('start_date')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        return array_values(array_unique($fallback));
    }
}
