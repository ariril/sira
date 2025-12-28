<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MyPerformanceController extends Controller
{
    public function index(): View
    {
        $userId = (int) Auth::id();

        $period = AssessmentPeriodGuard::resolveActive();

        $metrics = [
            'attendance_days' => null,
            'patient_count' => null,
        ];

        if ($period) {
            // Absensi: tampil hanya jika sudah ada import batch periode ini
            $attendanceAvailable = Schema::hasTable('attendance_import_batches')
                && DB::table('attendance_import_batches')->where('assessment_period_id', $period->id)->exists();

            if ($attendanceAvailable && Schema::hasTable('attendances')) {
                $metrics['attendance_days'] = (int) DB::table('attendances')
                    ->where('user_id', $userId)
                    ->whereDate('attendance_date', '>=', $period->start_date)
                    ->whereDate('attendance_date', '<=', $period->end_date)
                    ->where('attendance_status', 'HADIR')
                    ->count();
            }

            // Jumlah pasien ditangani: tampil hanya jika sudah ada metric import batch periode ini
            $patientAvailable = Schema::hasTable('metric_import_batches')
                && DB::table('metric_import_batches')->where('assessment_period_id', $period->id)->exists();

            if ($patientAvailable && Schema::hasTable('imported_criteria_values') && Schema::hasTable('performance_criterias')) {
                $metrics['patient_count'] = (float) DB::table('imported_criteria_values as icv')
                    ->join('performance_criterias as pc', 'pc.id', '=', 'icv.performance_criteria_id')
                    ->where('icv.user_id', $userId)
                    ->where('icv.assessment_period_id', $period->id)
                    ->where('pc.name', 'like', '%Pasien%')
                    ->sum('icv.value_numeric');
            }
        }

        return view('pegawai_medis.my_performance.index', [
            'period' => $period,
            'metrics' => $metrics,
        ]);
    }
}
