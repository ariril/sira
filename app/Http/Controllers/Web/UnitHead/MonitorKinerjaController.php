<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Profession;
use App\Models\User;
use App\Services\UnitHead\PerformanceMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MonitorKinerjaController extends Controller
{
    public function __construct(
        private readonly PerformanceMonitorService $monitorSvc,
    ) {
    }

    public function index(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();

        $periods = AssessmentPeriod::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'start_date', 'end_date']);

        $defaultPeriodId = AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_ACTIVE)
            ->orderByDesc('start_date')
            ->value('id');
        if (!$defaultPeriodId) {
            $defaultPeriodId = AssessmentPeriod::query()->orderByDesc('start_date')->value('id');
        }

        $periodId = (int) ($request->query('period_id') ?: $defaultPeriodId ?: 0);
        $period = $periodId > 0 ? AssessmentPeriod::query()->find($periodId) : null;
        if (!$period) {
            abort(404);
        }

        $mode = $this->monitorSvc->resolveMode($period, (string) $request->query('mode'));

        $filters = [
            'q' => (string) $request->query('search', ''),
            'profession_id' => $request->query('profession_id'),
            'status' => (string) $request->query('status', ''),
        ];

        $members = $this->monitorSvc->paginateUnitMembers($actor, $period, $mode, $filters, 15);

        $professions = Profession::query()->orderBy('name')->get(['id', 'name']);

        $statusOptions = [
            'empty' => 'Belum dinilai (skor kosong)',
            'filled' => 'Terisi (skor ada)',
            'pending' => 'Menunggu Validasi',
            'validated' => 'Tervalidasi',
            'rejected' => 'Ditolak',
        ];

        return view('kepala_unit.monitor_kinerja.index', [
            'periods' => $periods,
            'period' => $period,
            'mode' => $mode,
            'members' => $members,
            'professions' => $professions,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function show(Request $request, int $period, int $user)
    {
        /** @var User $actor */
        $actor = $request->user();

        $periodModel = AssessmentPeriod::query()->findOrFail($period);
        $target = User::query()->findOrFail($user);

        $mode = $this->monitorSvc->resolveMode($periodModel, (string) $request->query('mode'));

        Gate::authorize('monitor-kinerja-view', [$periodModel, $target, $mode]);

        $detail = $this->monitorSvc->getUserPerformanceDetail($actor, $periodModel, $target, $mode);

        return view('kepala_unit.monitor_kinerja.show', [
            'period' => $periodModel,
            'mode' => $mode,
            'target' => $target,
            'detail' => $detail,
        ]);
    }
}
