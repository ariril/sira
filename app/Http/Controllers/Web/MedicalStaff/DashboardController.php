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
            'recent_reviews' => collect(),
            'remunerasi_terakhir' => null,
            'nilai_kinerja_terakhir' => null,
            'jadwal_mendatang' => collect(),
        ];

        if (Schema::hasTable('ulasan_items')) {
            $from = now()->subDays(30);
            $me['avg_rating_30d'] = DB::table('ulasan_items')
                ->where('tenaga_medis_id',$userId)
                ->where('created_at','>=',$from)
                ->avg('rating');

            $me['total_review_30d'] = DB::table('ulasan_items')
                ->where('tenaga_medis_id',$userId)
                ->where('created_at','>=',$from)
                ->count();

            $me['recent_reviews'] = DB::table('ulasan_items as ui')
                ->join('ulasans as ul','ul.id','=','ui.ulasan_id')
                ->select('ui.rating','ui.komentar','ul.created_at')
                ->where('ui.tenaga_medis_id',$userId)
                ->orderByDesc('ul.created_at')->limit(8)->get();
        }

        if (Schema::hasTable('remunerasis')) {
            $me['remunerasi_terakhir'] = DB::table('remunerasis')
                ->where('user_id',$userId)
                ->orderByDesc('id')->first();
        }

        if (Schema::hasTable('penilaian_kinerjas')) {
            $me['nilai_kinerja_terakhir'] = DB::table('penilaian_kinerjas')
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->value('skor_total_wsm');
        }

        if (Schema::hasTable('jadwal_dokters')) {
            $me['jadwal_mendatang'] = DB::table('jadwal_dokters')
                ->where('user_id',$userId)
                ->where('tanggal','>=', now()->toDateString())
                ->orderBy('tanggal')->orderBy('jam_mulai')
                ->limit(10)->get();
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
                        'next' => url('/pegawai-medis/additional-contributions'),
                    ]);
                }
            }
        }

        // Latest criteria weight change by unit head (per unit)
        $unitId = optional(Auth::user())->unit_id;
        if ($unitId && Schema::hasTable('unit_criteria_weights') && Schema::hasTable('assessment_periods')) {
            $criteriaNotice = DB::table('unit_criteria_weights as w')
                ->join('assessment_periods as ap', 'ap.id', '=', 'w.assessment_period_id')
                ->select('w.id', 'w.updated_at', 'ap.name as period_name')
                ->where('w.unit_id', $unitId)
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
