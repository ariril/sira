<?php

namespace App\Services\MultiRater;

use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Assessment360ScoreFallbackService
{
    public function resolvePreviousPeriodId(int $periodId): ?int
    {
        $periodId = (int) $periodId;
        if ($periodId <= 0 || !Schema::hasTable('assessment_periods')) {
            return null;
        }

        $currentStart = DB::table('assessment_periods')
            ->where('id', $periodId)
            ->value('start_date');

        if (!$currentStart) {
            return null;
        }

        $frozenStatuses = [
            AssessmentPeriod::STATUS_LOCKED,
            AssessmentPeriod::STATUS_APPROVAL,
            AssessmentPeriod::STATUS_REVISION,
            AssessmentPeriod::STATUS_CLOSED,
            AssessmentPeriod::STATUS_ARCHIVED,
        ];

        $prevId = DB::table('assessment_periods')
            ->whereIn('status', $frozenStatuses)
            ->where('start_date', '<', $currentStart)
            ->orderByDesc('start_date')
            ->value('id');

        return $prevId ? (int) $prevId : null;
    }

    /**
     * @param array<int> $assesseeIds
     * @return array{period_id:?int, map: array<string,array{avg:float,n:int,source:string}>}
     */
    public function getFallbackAverages(int $periodId, int $criteriaId, array $assesseeIds): array
    {
        $periodId = (int) $periodId;
        $criteriaId = (int) $criteriaId;
        $assesseeIds = array_values(array_unique(array_map('intval', $assesseeIds)));

        if ($periodId <= 0 || $criteriaId <= 0 || empty($assesseeIds)) {
            return ['period_id' => null, 'map' => []];
        }

        if (!Schema::hasTable('multi_rater_assessments') || !Schema::hasTable('multi_rater_assessment_details')) {
            return ['period_id' => null, 'map' => []];
        }

        $prevPeriodId = $this->resolvePreviousPeriodId($periodId);
        if (!$prevPeriodId) {
            return ['period_id' => null, 'map' => []];
        }

        $rows = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->selectRaw('mra.assessee_id as user_id, mra.assessor_type as assessor_type, mra.assessor_level as assessor_level, mra.assessor_profession_id as assessor_profession_id, AVG(d.score) as avg_score, COUNT(*) as n')
            ->where('mra.assessment_period_id', $prevPeriodId)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $assesseeIds)
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id', 'mra.assessor_type', 'mra.assessor_level', 'mra.assessor_profession_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            $type = (string) ($row->assessor_type ?? '');
            if ($uid <= 0 || $type === '') {
                continue;
            }
            $level = $row->assessor_level === null ? 0 : (int) $row->assessor_level;
            $profId = $row->assessor_profession_id === null ? 0 : (int) $row->assessor_profession_id;
            $avg = (float) ($row->avg_score ?? 0.0);
            $n = (int) ($row->n ?? 0);
            if ($n <= 0) {
                continue;
            }
            $key = $uid . '|' . $type . '|' . $level . '|' . $profId;
            $map[$key] = [
                'avg' => $avg,
                'n' => $n,
                'source' => 'previous_period',
            ];
        }

        return ['period_id' => (int) $prevPeriodId, 'map' => $map];
    }
}
