<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\AttendanceImportBatch;
use App\Models\Attendance;
use App\Models\AssessmentApproval;
use App\Models\UnitRemunerationAllocation;
use App\Models\Remuneration;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'attendance_batches' => AttendanceImportBatch::count(),
            'attendances' => Attendance::count(),
            'approvals_pending' => AssessmentApproval::where('status', 'pending')->count(),
            'unit_allocations' => UnitRemunerationAllocation::count(),
        ];

        $recentApprovals = AssessmentApproval::with(['assessment.user', 'assessment.period'])
            ->latest()->take(5)->get();

        $recentAllocations = UnitRemunerationAllocation::with(['unit', 'period'])
            ->latest()->take(5)->get();

        return view('administrasi.dashboard', compact('stats', 'recentApprovals', 'recentAllocations'));
    }
}
