<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // ====== USER STATS (tetap) ======
        $stats = [
            'total_user' => User::count(),
            'total_unit' => Unit::count(),
            'total_profesi' => Profession::count(),
            'pegawai_medis' => User::where('role', 'pegawai_medis')->count(),
            'kepala_unit' => User::where('role', 'kepala_unit')->count(),
            'kepala_poliklinik' => User::where('role', 'kepala_poliklinik')->count(),
            // Backward-compatible count, but normalize to 'admin_rs'
            'admin_rs' => User::where('role', 'admin_rs')->orWhere('role', 'administrasi')->count(),
            'super_admin' => User::where('role', 'super_admin')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
        ];

        // ====== PERIODE AKTIF ======
        $activePeriod = AssessmentPeriod::where('is_active', 1)->latest('id')->first();

        // ====== PROGRES BOBOT KRITERIA UNIT (periode aktif) ======
        $weightsTotal = $activePeriod
            ? DB::table('unit_criteria_weights')->where('assessment_period_id', $activePeriod->id)->count()
            : 0;

        $weightsActive = $activePeriod
            ? DB::table('unit_criteria_weights')->where('assessment_period_id', $activePeriod->id)->where('status', 'active')->count()
            : 0;

        $weightsPct = $weightsTotal > 0 ? round(($weightsActive / $weightsTotal) * 100, 1) : 0;

        // ====== APPROVAL PENDING PER LEVEL ======
        $pendingL1 = DB::table('assessment_approvals')->where('status', 'pending')->where('level', 1)->count();
        $pendingL2 = DB::table('assessment_approvals')->where('status', 'pending')->where('level', 2)->count();
        $pendingL3 = DB::table('assessment_approvals')->where('status', 'pending')->where('level', 3)->count();

        // ====== CAKUPAN PENILAIAN (periode aktif) ======
        $expectedAssess = User::where('role', 'pegawai_medis')->count();
        $submittedAssess = $activePeriod
            ? DB::table('performance_assessments')->where('assessment_period_id', $activePeriod->id)->count()
            : 0;
        $coveragePct = $expectedAssess > 0 ? round(($submittedAssess / $expectedAssess) * 100, 1) : 0;

        // ====== IMPORT ABSENSI TERAKHIR ======
        $lastBatch = DB::table('attendance_import_batches')->orderByDesc('imported_at')->first();
        $lastBatchInfo = [
            'at' => optional($lastBatch)->imported_at,
            'total' => (int)($lastBatch->total_rows ?? 0),
            'success' => (int)($lastBatch->success_rows ?? 0),
            'failed' => (int)($lastBatch->failed_rows ?? 0),
            'success_rate' => ($lastBatch && $lastBatch->total_rows > 0)
                ? round($lastBatch->success_rows / $lastBatch->total_rows * 100, 1)
                : null,
        ];

        // ====== STATUS REMUNERASI (periode aktif) ======
        $remunerasi = [
            'total_nominal' => 0,
            'count' => 0,
            'published' => 0,
            'by_status' => ['Belum Dibayar' => 0, 'Dibayar' => 0, 'Ditahan' => 0],
        ];

        if ($activePeriod) {
            $remunerasi['total_nominal'] = (float)DB::table('remunerations')
                ->where('assessment_period_id', $activePeriod->id)->sum('amount');

            $remunerasi['count'] = DB::table('remunerations')
                ->where('assessment_period_id', $activePeriod->id)->count();

            $remunerasi['published'] = DB::table('remunerations')
                ->where('assessment_period_id', $activePeriod->id)
                ->whereNotNull('published_at')->count();

            $byStatus = DB::table('remunerations')
                ->select('payment_status', DB::raw('COUNT(*) as total'))
                ->where('assessment_period_id', $activePeriod->id)
                ->groupBy('payment_status')
                ->pluck('total', 'payment_status')->all();

            $remunerasi['by_status'] = array_merge($remunerasi['by_status'], $byStatus);
        }

        // ====== PENILAIAN PENDING (tetap) ======
        $penilaianPending = DB::table('performance_assessments')->where('validation_status', 'Menunggu Validasi')->count();

        $kinerja = [
            'periode_aktif' => $activePeriod,
            'penilaian_pending' => $penilaianPending,
            // tambahan
            'weights_total' => $weightsTotal,
            'weights_active' => $weightsActive,
            'weights_pct' => $weightsPct,
            'coverage_expected' => $expectedAssess,
            'coverage_submitted' => $submittedAssess,
            'coverage_pct' => $coveragePct,
            'pending_l1' => $pendingL1,
            'pending_l2' => $pendingL2,
            'pending_l3' => $pendingL3,
            'last_batch' => $lastBatchInfo,
            'remunerasi' => $remunerasi,
        ];

        // ====== (Opsional) SECTION Mutu/Review untuk bawah halaman ======
        $from = now()->subDays(30);
        $avgRating30d = DB::table('review_details')->where('created_at', '>=', $from)->avg('rating');
        $totalReview30d = DB::table('review_details')->where('created_at', '>=', $from)->count();
        $topTenagaMedis = DB::table('performance_assessments as pa')
            ->join('users as u', 'u.id', '=', 'pa.user_id')
            ->selectRaw('u.id, u.name as nama, u.position as jabatan, AVG(pa.total_wsm_score) as avg_rating, COUNT(*) as total_ulasan')
            ->where('u.role', 'pegawai_medis')
            ->groupBy('u.id', 'u.name', 'u.position')
            ->orderByDesc('avg_rating')->limit(5)->get();

        $review = [
            'avg_rating_30d' => $avgRating30d,
            'total_30d' => $totalReview30d,
            'top_tenaga_medis' => $topTenagaMedis,
        ];

        return view('super_admin.dashboard', compact('stats', 'kinerja', 'review'));
    }
}
