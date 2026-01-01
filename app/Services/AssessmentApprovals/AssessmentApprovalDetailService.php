<?php

namespace App\Services\AssessmentApprovals;

use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalDetailService
{
    public function __construct(private readonly PerformanceScoreService $scoreSvc)
    {
    }

    /**
     * Compute breakdown for the assessment's own period using active weights for that (unit, period).
     * This is intentionally independent of the period "is_active" flag, because approvals happen when
     * periods are locked/approval.
     *
     * @return array{hasWeights: bool, total: ?float, sumWeight: float, rows: array<int, array{criteria_id:int,criteria_name:string,weight:float,score_wsm:float,score_relative_unit:float,contribution:float}>}
     */
    public function getBreakdown(PerformanceAssessment $pa): array
    {
        $period = $pa->assessmentPeriod;
        $unitId = (int) ($pa->user?->unit_id ?? 0);
        $professionId = $pa->user?->profession_id;
        $periodId = (int) ($pa->assessment_period_id ?? 0);

        if (!$period || $unitId <= 0 || $periodId <= 0) {
            return ['hasWeights' => false, 'total' => null, 'sumWeight' => 0.0, 'rows' => []];
        }

        $groupUserIds = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId)
            ->when($professionId === null, fn($q) => $q->whereNull('profession_id'))
            ->when($professionId !== null, fn($q) => $q->where('profession_id', (int) $professionId))
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->all();

        if (empty($groupUserIds)) {
            return ['hasWeights' => false, 'total' => null, 'sumWeight' => 0.0, 'rows' => []];
        }

        $calc = $this->scoreSvc->calculate($unitId, $period, $groupUserIds, $professionId);

        $uid = (int) ($pa->user_id ?? 0);
        $userRow = $uid > 0 ? ($calc['users'][$uid] ?? null) : null;
        $sumWeight = $userRow ? (float) ($userRow['sum_weight'] ?? 0.0) : 0.0;
        $total = $userRow['total_wsm'] ?? null;

        if ($sumWeight <= 0.0 || $total === null) {
            return ['hasWeights' => false, 'total' => null, 'sumWeight' => 0.0, 'rows' => []];
        }

        $rows = [];
        foreach (($userRow['criteria'] ?? []) as $r) {
            if (!($r['included_in_wsm'] ?? false)) {
                continue;
            }
            $weight = (float) ($r['weight'] ?? 0.0);
            // Total WSM uses relative score (0–100).
            $scoreWsm = (float) ($r['nilai_relativ_unit'] ?? 0.0);
            $rows[] = [
                'criteria_id' => (int) ($r['criteria_id'] ?? 0),
                'criteria_name' => (string) ($r['criteria_name'] ?? '-'),
                'weight' => $weight,
                'score_wsm' => $scoreWsm,
                'score_relative_unit' => (float) ($r['nilai_relativ_unit'] ?? 0.0),
                'contribution' => ($weight / $sumWeight) * $scoreWsm,
            ];
        }

        return [
            'hasWeights' => true,
            'total' => (float) $total,
            'sumWeight' => $sumWeight,
            'rows' => $rows,
        ];
    }

    public function getRawImportedValues(PerformanceAssessment $pa): Collection
    {
        $userId = (int) ($pa->user_id ?? 0);
        $periodId = (int) ($pa->assessment_period_id ?? 0);
        if ($userId <= 0 || $periodId <= 0) {
            return collect();
        }

        return DB::table('imported_criteria_values as v')
            ->leftJoin('performance_criterias as c', 'c.id', '=', 'v.performance_criteria_id')
            ->leftJoin('metric_import_batches as b', 'b.id', '=', 'v.import_batch_id')
            ->where('v.user_id', $userId)
            ->where('v.assessment_period_id', $periodId)
            ->orderBy('v.performance_criteria_id')
            ->select([
                'v.performance_criteria_id',
                'c.name as criteria_name',
                'v.value_numeric',
                'v.value_datetime',
                'v.value_text',
                'b.file_name as batch_file',
                'v.import_batch_id',
            ])
            ->get();
    }
}
