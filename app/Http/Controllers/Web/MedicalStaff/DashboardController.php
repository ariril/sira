<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;
use App\Models\AssessmentPeriod;
use App\Services\AdditionalTasks\AdditionalTaskClaimNoticeService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdditionalTaskClaimNoticeService $additionalTaskClaimNoticeService,
    ) {
    }

    public function index()
    {
        $userId = Auth::id();

        if (request()->boolean('ack_notice') && request()->filled('key')) {
            session()->put('notice_seen_' . request('key'), true);

            $next = request('next');
            if ($next) {
                return redirect()->to($next);
            }

            return redirect()->route('pegawai_medis.dashboard');
        }

        $activePeriod = null;
        if (Schema::hasTable('assessment_periods')) {
            $activePeriod = DB::table('assessment_periods')
                ->where('status', AssessmentPeriod::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();
        }

        $me = [
            'avg_rating_30d' => null,
            'total_review_30d' => 0,
            'remunerasi_terakhir' => null,
            'nilai_kinerja_terakhir' => null,
        ];

        // Reviews (30d) — use current schema: reviews + review_details
        if (Schema::hasTable('review_details') && Schema::hasTable('reviews')) {
            $from = now()->subDays(30);

            $base = DB::table('review_details as rd')
                ->join('reviews as r', 'r.id', '=', 'rd.review_id')
                ->where('rd.medical_staff_id', $userId)
                ->whereNotNull('rd.rating')
                // Count only approved reviews so KPI aligns with public/approved data.
                ->where('r.status', 'approved')
                ->where('r.created_at', '>=', $from);

            $me['avg_rating_30d'] = (clone $base)->avg('rd.rating');
            $me['total_review_30d'] = (int) (clone $base)->count('rd.rating');
        }

        // Remunerasi terakhir — use current schema: remunerations
        if (Schema::hasTable('remunerations')) {
            $me['remunerasi_terakhir'] = DB::table('remunerations as r')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'r.assessment_period_id')
                ->where('r.user_id', $userId)
                ->select('r.amount', 'r.payment_status', 'r.payment_date', 'r.assessment_period_id', 'ap.name as period_name', 'ap.start_date', 'ap.end_date')
                ->orderByDesc('ap.start_date')
                ->orderByDesc('r.assessment_period_id')
                ->first();
        }

        // Skor kinerja terakhir — use current schema: performance_assessments
        if (Schema::hasTable('performance_assessments')) {
            $me['nilai_kinerja_terakhir'] = DB::table('performance_assessments as pa')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->where('pa.user_id', $userId)
                ->whereNotNull('pa.total_wsm_score')
                ->orderByDesc('ap.start_date')
                ->orderByDesc('pa.assessment_date')
                ->orderByDesc('pa.assessment_period_id')
                ->value('pa.total_wsm_score');
        }

        // One-time banner: latest assessment fully approved (Level 3)
        $approvalBanner = null;
        $rejectedClaim = null;
        $criteriaNotice = null;
        if (Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments')) {
            $latestApproved = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->join('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->where('pa.user_id', $userId)
                ->where('aa.level', 3)
                ->where('aa.status', 'approved')
                ->orderByDesc('aa.acted_at')
                ->select('aa.performance_assessment_id as assessment_id', 'ap.name as period_name', 'aa.acted_at')
                ->first();

            if ($latestApproved) {
                $actedAtTs = $latestApproved->acted_at ? Carbon::parse($latestApproved->acted_at)->timestamp : null;
                $key = 'approval_' . $latestApproved->assessment_id . '_' . $actedAtTs;
                if (!session()->has('notice_seen_' . $key)) {
                    $approvalBanner = [
                        'period_name' => $latestApproved->period_name,
                        'ack_url' => route('pegawai_medis.dashboard', [
                            'ack_notice' => 1,
                            'key' => $key,
                            'next' => route('pegawai_medis.assessments.index'),
                        ]),
                    ];
                }
            }
        }

        // Latest rejected additional task claim (for notice)
        if (Schema::hasTable('additional_task_claims') && Schema::hasTable('additional_tasks')) {
            $rejectedClaim = $this->additionalTaskClaimNoticeService->latestRejectedClaimForUser((int) $userId);

            if ($rejectedClaim) {
                $updatedTs = $rejectedClaim->updated_at ? Carbon::parse($rejectedClaim->updated_at)->timestamp : null;
                $key = 'rejected_claim_' . $rejectedClaim->id . '_' . $updatedTs;
                if (session()->has('notice_seen_' . $key)) {
                    $rejectedClaim = null;
                } else {
                    $rejectedClaim->ack_url = route('pegawai_medis.dashboard', [
                        'ack_notice' => 1,
                        'key' => $key,
                        'next' => url('/pegawai-medis/additional-tasks'),
                    ]);
                }
            }
        }

        // Latest criteria weight change by unit head (per unit)
        // IMPORTANT: only show this notice when there is an ACTIVE period and the weights are ACTIVE for that period.
        $unitId = optional(Auth::user())->unit_id;
        if ($unitId && $activePeriod && Schema::hasTable('unit_criteria_weights') && Schema::hasTable('assessment_periods')) {
            $criteriaNotice = DB::table('unit_criteria_weights as w')
                ->join('assessment_periods as ap', 'ap.id', '=', 'w.assessment_period_id')
                ->select('w.id', 'w.updated_at', 'ap.name as period_name')
                ->where('w.unit_id', $unitId)
                ->where('w.assessment_period_id', (int) $activePeriod->id)
                ->where('w.status', 'active')
                ->orderByDesc('w.updated_at')
                ->first();

            if ($criteriaNotice) {
                $updatedTs = $criteriaNotice->updated_at ? Carbon::parse($criteriaNotice->updated_at)->timestamp : null;
                $key = 'criteria_change_' . $criteriaNotice->id . '_' . $updatedTs;
                if (session()->has('notice_seen_' . $key)) {
                    $criteriaNotice = null;
                } else {
                    $criteriaNotice->ack_url = route('pegawai_medis.dashboard', [
                        'ack_notice' => 1,
                        'key' => $key,
                        'next' => url('/pegawai-medis/unit-criteria-weights'),
                    ]);
                }
            }
        }

        return view('pegawai_medis.dashboard', [
            'me' => $me,
            'approvalBanner' => $approvalBanner,
            'rejectedClaim' => $rejectedClaim,
            'criteriaNotice' => $criteriaNotice,
            'activePeriod' => $activePeriod,
        ]);
    }
}
