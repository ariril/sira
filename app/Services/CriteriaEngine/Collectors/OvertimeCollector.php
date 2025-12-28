<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class OvertimeCollector implements CriteriaCollector
{
    public function key(): string { return 'overtime'; }

    public function label(): string { return 'Lembur'; }

    public function type(): string { return 'benefit'; }

    public function source(): string { return 'system'; }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (!$period->start_date || !$period->end_date || empty($userIds)) {
            return [];
        }

        // Current schema does not store overtime minutes; we count overtime occurrences.
        return DB::table('attendances')
            ->selectRaw('user_id, SUM(CASE WHEN overtime_shift = 1 OR overtime_end IS NOT NULL THEN 1 ELSE 0 END) as total_overtime')
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->groupBy('user_id')
            ->pluck('total_overtime', 'user_id')
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
            ->where(function ($q) {
                $q->where('a.overtime_shift', 1)->orWhereNotNull('a.overtime_end');
            })
            ->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Belum ada data lembur pada periode ini.'];
    }
}
