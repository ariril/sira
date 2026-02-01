<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\ReviewInvitation;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReviewInvitationController extends Controller
{
    public function index(Request $request): View
    {
        if (!Schema::hasTable('review_invitations')) {
            return view('admin_rs.review_invitations.index', [
                'items' => null,
                'period' => null,
                'periodWarning' => 'Tabel review_invitations belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ]);
        }

        ['period' => $period, 'periodWarning' => $periodWarning, 'periodOptions' => $periodOptions, 'selectedPeriodId' => $selectedPeriodId] = $this->resolvePeriodContext($request);

        $query = ReviewInvitation::query()->with('unit');

        if ($period && Schema::hasColumn('review_invitations', 'assessment_period_id')) {
            $query->where('assessment_period_id', (int) $period->id);
        }

        $items = $query
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin_rs.review_invitations.index', [
            'items' => $items,
            'period' => $period,
            'periodWarning' => $periodWarning,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ]);
    }

    /**
     * When there is both an active (date-based) period and a latest LOCKED period, allow user to choose.
     * Default selection prefers LOCKED (so monthly monitoring can still target the locked month).
     *
     * @return array{period:?AssessmentPeriod,periodWarning:?string,periodOptions:array<int,string>,selectedPeriodId:?int}
     */
    private function resolvePeriodContext(Request $request): array
    {
        if (!Schema::hasTable('assessment_periods')) {
            return [
                'period' => null,
                'periodWarning' => 'Periode penilaian belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ];
        }

        $periods = AssessmentPeriod::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'start_date']);

        if ($periods->isEmpty()) {
            return [
                'period' => null,
                'periodWarning' => 'Periode penilaian belum tersedia.',
                'periodOptions' => [],
                'selectedPeriodId' => null,
            ];
        }

        $active = AssessmentPeriodGuard::resolveActive();
        $defaultId = $active?->id ? (int) $active->id : (int) $periods->first()->id;

        $periodOptions = $periods
            ->mapWithKeys(fn (AssessmentPeriod $p) => [
                (int) $p->id => (string) ($p->name ?? '-'),
            ])
            ->all();

        $requestedId = $request->query('period_id');
        $requestedId = $requestedId !== null ? (int) $requestedId : null;

        $selectedPeriodId = ($requestedId && isset($periodOptions[$requestedId])) ? $requestedId : $defaultId;
        $selectedPeriod = $periods->firstWhere('id', $selectedPeriodId);

        return [
            'period' => $selectedPeriod,
            'periodWarning' => null,
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
        ];
    }
}
