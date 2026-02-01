<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class ContributionCollector implements CriteriaCollector
{
    public function key(): string { return 'contribution'; }

    public function label(): string { return 'Tugas Tambahan'; }

    public function type(): string { return 'benefit'; }

    public function source(): string { return 'system'; }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $claimPoints = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->selectRaw('c.user_id, COALESCE(SUM(COALESCE(c.awarded_points, t.points, 0)),0) as total_score')
            ->whereIn('c.user_id', $userIds)
            ->where('t.assessment_period_id', $period->id)
            ->where('t.unit_id', $unitId)
            ->whereIn('c.status', ['approved', 'completed'])
            ->groupBy('c.user_id')
            ->pluck('total_score', 'c.user_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $out[$uid] = (float) ($claimPoints[$uid] ?? 0.0);
        }

        return $out;
    }

    public function readiness(AssessmentPeriod $period, int $unitId): array
    {
        if ($unitId <= 0 || (int) ($period->id ?? 0) <= 0) {
            return ['status' => 'missing_data', 'message' => 'Periode/unit belum valid.'];
        }

        $count = (int) DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->where('t.assessment_period_id', (int) $period->id)
            ->where('t.unit_id', $unitId)
            ->whereIn('c.status', ['approved', 'completed'])
            ->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Belum ada klaim tugas tambahan yang disetujui pada periode ini.'];
    }
}
