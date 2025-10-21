<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        if (!$user) abort(403);

        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Core stats for Admin RS
        $stats = [
            'attendance_batches' => Schema::hasTable('attendance_import_batches')
                ? (int) DB::table('attendance_import_batches')->count() : 0,
            'attendances'        => Schema::hasTable('attendances')
                ? (int) DB::table('attendances')->count() : 0,
            'approvals_pending'  => Schema::hasTable('assessment_approvals')
                ? (int) DB::table('assessment_approvals')->where('status','pending')->count() : 0,
            'unit_allocations'   => Schema::hasTable('unit_remuneration_allocations')
                ? (int) DB::table('unit_remuneration_allocations')->count() : 0,
        ];

        // Recent approvals table (limit 8)
        $recentApprovals = collect();
        if (Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments')) {
            $recentApprovals = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw('aa.id, aa.status, aa.level, pa.id as assessment_id, u.name as user_name, ap.name as period_name, aa.created_at')
                ->orderByDesc('aa.id')
                ->limit(8)
                ->get();
        }

        // Recent unit allocations list (limit 8)
        $recentAllocations = collect();
        if (Schema::hasTable('unit_remuneration_allocations')) {
            $recentAllocations = DB::table('unit_remuneration_allocations as ura')
                ->leftJoin('units as un', 'un.id', '=', 'ura.unit_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'ura.assessment_period_id')
                ->selectRaw('ura.id, ura.amount, ura.published_at, un.name as unit_name, ap.name as period_name, ura.created_at')
                ->orderByDesc('ura.id')
                ->limit(8)
                ->get();
        }

        // Notifications (no DB schema changes; computed in code)
        $notifications = [];
        if ($stats['approvals_pending'] > 0) {
            $notifications[] = [
                'type' => 'warning',
                'text' => $stats['approvals_pending'] . ' penilaian menunggu review awal (Level 1).',
                'href' => route('admin_rs.assessments.pending'),
            ];
        }
        // Remind if no attendance was recorded today but exists yesterday
        $attToday = Schema::hasTable('attendances') ? DB::table('attendances')->whereDate('attendance_date',$today)->count() : 0;
        $attYday  = Schema::hasTable('attendances') ? DB::table('attendances')->whereDate('attendance_date',$yesterday)->count() : 0;
        if ($attToday === 0 && $attYday > 0) {
            $notifications[] = [
                'type' => 'info',
                'text' => 'Belum ada data absensi yang masuk hari ini. Pastikan proses impor berjalan.',
                'href' => route('admin_rs.attendances.import.form'),
            ];
        }

        return view('admin_rs.dashboard', compact('stats','recentApprovals','recentAllocations','notifications'));
    }
}
