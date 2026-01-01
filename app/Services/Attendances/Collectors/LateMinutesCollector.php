<?php

namespace App\Services\Attendances\Collectors;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class LateMinutesCollector implements CriteriaCollector
{
    public function key(): string { return 'late_minutes'; }

    public function label(): string { return 'Keterlambatan'; }

    public function type(): string { return 'cost'; }

    public function source(): string { return 'system'; }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (!$period->start_date || !$period->end_date || empty($userIds)) {
            return [];
        }

        return DB::table('attendances')
            ->selectRaw('user_id, COALESCE(SUM(late_minutes),0) as total_late')
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->groupBy('user_id')
            ->pluck('total_late', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();
    }

    public function readiness(AssessmentPeriod $period, int $unitId): array
    {
        if (!$period->start_date || !$period->end_date || $unitId <= 0) {
            return ['status' => 'missing_data', 'message' => 'Periode/unit belum valid.'];
        }

        $count = DB::table('attendances as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('u.unit_id', $unitId)
            ->whereBetween('a.attendance_date', [$period->start_date, $period->end_date])
            ->whereNotNull('a.late_minutes')
            ->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Data keterlambatan belum tersedia dari import absensi.'];
    }
}
