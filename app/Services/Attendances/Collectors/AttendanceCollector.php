<?php

namespace App\Services\Attendances\Collectors;

use App\Enums\AttendanceStatus;
use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class AttendanceCollector implements CriteriaCollector
{
    public function key(): string { return 'attendance'; }

    public function label(): string { return 'Kehadiran'; }

    public function type(): string { return 'benefit'; }

    public function source(): string { return 'system'; }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (!$period->start_date || !$period->end_date || empty($userIds)) {
            return [];
        }

        return DB::table('attendances')
            ->selectRaw('user_id, COUNT(*) as total_hadir')
            ->whereIn('user_id', $userIds)
            ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
            ->where(function ($q) {
                $q->whereIn('attendance_status', [
                    AttendanceStatus::HADIR->value,
                    AttendanceStatus::TERLAMBAT->value,
                ])->whereNotNull('check_in')->whereNotNull('check_out');

                $q->orWhereIn('attendance_status', [
                    AttendanceStatus::LIBUR_UMUM->value,
                    AttendanceStatus::LIBUR_RUTIN->value,
                ]);
            })
            ->groupBy('user_id')
            ->pluck('total_hadir', 'user_id')
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
            ->whereNotNull('a.check_in')
            ->whereNotNull('a.check_out')
            ->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Data absensi pada periode ini belum ada (belum import).'];
    }
}
