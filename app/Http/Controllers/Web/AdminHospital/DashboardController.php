<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaProposal;
use App\Enums\CriteriaProposalStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Models\AssessmentPeriod;
use Carbon\Carbon;
use App\Support\AssessmentPeriodGuard;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        if (!$user) abort(403);

        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Core stats for Admin RS (refined queries)
        $stats = [
            'attendance_batches' => Schema::hasTable('attendance_import_batches')
                ? (int) DB::table('attendance_import_batches')->count() : 0,
            'attendances'        => Schema::hasTable('attendances')
                ? (int) DB::table('attendances')->count() : 0,
            // Level 1 pending approvals only
            'approvals_pending_l1' => (Schema::hasTable('assessment_approvals')
                ? (int) DB::table('assessment_approvals')->where('level',1)->where('status','pending')->count() : 0),
            'unit_allocations'   => Schema::hasTable('unit_profession_remuneration_allocations')
                ? (int) DB::table('unit_profession_remuneration_allocations')->count() : 0,
            // Remunerations pending publish (published_at null)
            'remunerations_draft' => (Schema::hasTable('remunerations')
                ? (int) DB::table('remunerations')->whereNull('published_at')->count() : 0),
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
        if (Schema::hasTable('unit_profession_remuneration_allocations')) {
            $recentAllocations = DB::table('unit_profession_remuneration_allocations as ura')
                ->leftJoin('units as un', 'un.id', '=', 'ura.unit_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'ura.assessment_period_id')
                ->selectRaw('ura.id, ura.amount, ura.published_at, un.name as unit_name, ap.name as period_name, ura.created_at')
                ->orderByDesc('ura.id')
                ->limit(8)
                ->get();
        }

        // Notifications (no DB schema changes; computed in code)
        $notifications = [];
        if ($stats['approvals_pending_l1'] > 0) {
            $notifications[] = [
                'type' => 'warning',
                'text' => $stats['approvals_pending_l1'] . ' penilaian menunggu review awal (Level 1).',
                'href' => route('admin_rs.assessments.pending') . '?status=pending_l1',
            ];
        }
        // Active period (date-based) + latest locked
        $activePeriod = AssessmentPeriodGuard::resolveActive();
        $lockedPeriod = Schema::hasTable('assessment_periods')
            ? AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_LOCKED)->orderByDesc('start_date')->first()
            : null;

        // Period used for import readiness notifications: prefer active, fallback to locked.
        $importPeriod = $activePeriod ?: $lockedPeriod;

        $activePeriodId = $activePeriod?->id;
        $activePeriodName = $activePeriod?->name;

        // Units without allocation: if there's a LOCKED period that isn't the active one, treat it as urgent.
        $allocationPeriod = null;
        $allocationType = 'warning';
        if ($lockedPeriod && (!$activePeriodId || (int) $lockedPeriod->id !== (int) $activePeriodId)) {
            $allocationPeriod = $lockedPeriod;
            $allocationType = 'error';
        } elseif ($activePeriod) {
            $allocationPeriod = $activePeriod;
        }

        if ($allocationPeriod && Schema::hasTable('units') && Schema::hasTable('unit_profession_remuneration_allocations')) {
            $unitCount = (int) DB::table('units')->count();
            $allocatedUnitIds = DB::table('unit_profession_remuneration_allocations')
                ->where('assessment_period_id', (int) $allocationPeriod->id)
                ->pluck('unit_id')->unique();
            $missingAllocCount = $unitCount - $allocatedUnitIds->count();

            if ($missingAllocCount > 0) {
                $suffix = $allocationType === 'error'
                    ? 'pada periode ' . ($allocationPeriod->name ?? '-') . '. Periode sudah dikunci, segera lengkapi sebelum proses approval.'
                    : 'pada periode aktif (' . ($activePeriodName ?? '-') . ').';

                $notifications[] = [
                    'type' => $allocationType,
                    'text' => $missingAllocCount . ' unit belum diberi alokasi ' . $suffix,
                    'href' => route('admin_rs.unit-remuneration-allocations.index') . '?period_id=' . (int) $allocationPeriod->id,
                ];
            }
        }

        // Remunerasi draft (belum publish)
        if ($stats['remunerations_draft'] > 0) {
            $notifications[] = [
                'type' => 'warning',
                'text' => $stats['remunerations_draft'] . ' data remunerasi menunggu untuk dipublish.',
                'href' => route('admin_rs.remunerations.index') . '?period_id=' . ($activePeriodId ?? ''),
            ];
        }

        // Dashboard notif Admin RS:
        // Jika ada periode yang sedang berjalan (date-based) ATAU status=LOCKED, tampilkan 2 notif import.
        if ($importPeriod) {
            $notifications[] = [
                'type' => 'info',
                'text' => 'Import Absensi sudah bisa diisi.',
                'href' => route('admin_rs.attendances.import.form'),
            ];
            $notifications[] = [
                'type' => 'info',
                'text' => 'Import Metric sudah bisa diisi.',
                'href' => route('admin_rs.metrics.index'),
            ];
        } else {
            $notifications[] = [
                'type' => 'error',
                'text' => 'Tidak ada periode berjalan atau periode terkunci saat ini. Proses import dan penilaian belum dapat dimulai.',
                'href' => route('admin_rs.assessment-periods.index'),
            ];
        }

        // Inactive criteria count (for awareness)
        if (Schema::hasTable('performance_criterias')) {
            $inactiveCriteria = (int) DB::table('performance_criterias')->where('is_active',false)->count();
            if ($inactiveCriteria > 0) {
                $notifications[] = [
                    'type' => 'info',
                    'text' => $inactiveCriteria . ' kriteria dalam keadaan nonaktif (bisa diaktifkan bila diperlukan).',
                    'href' => route('admin_rs.performance-criterias.index') . '?active=no',
                ];
            }
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

        // Notification for pending criteria proposals
        if (\Illuminate\Support\Facades\Schema::hasTable('criteria_proposals')) {
            $pendingCount = (int) CriteriaProposal::query()
                ->where('status', CriteriaProposalStatus::PROPOSED)
                ->count();
            if ($pendingCount > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'text' => $pendingCount . ' kriteria baru menunggu review (Usulan Kriteria).',
                    'href' => route('admin_rs.performance-criterias.index') . '#usulan-kriteria',
                ];
            }
        }

        return view('admin_rs.dashboard', compact('stats','recentApprovals','recentAllocations','notifications'));
    }
}
