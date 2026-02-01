<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyPerformanceController extends Controller
{
    public function index(PerformanceScoreService $scoreService): View
    {
        $userId = (int) Auth::id();
        /** @var User|null $user */
        $user = Auth::user();

        $period = AssessmentPeriodGuard::resolveActive();

        $data = null;
        $scope = [
            'unit' => $user?->unit?->name ?? null,
            'profession' => $user?->profession?->name ?? null,
        ];

        if ($period && $user) {
            $unitId = (int) ($user->unit_id ?? 0);
            $professionId = $user->profession_id !== null ? (int) $user->profession_id : null;

            if ($unitId > 0) {
                $groupUserIds = $this->resolveGroupUserIds($unitId, $professionId);
                if (empty($groupUserIds)) {
                    $groupUserIds = [$userId];
                }

                $out = $scoreService->calculateAllCriteria($unitId, $period, $groupUserIds, $professionId);
                $data = $out['users'][$userId] ?? null;

                // Fallback: ensure current user always has a row.
                if (!$data) {
                    $out = $scoreService->calculateAllCriteria($unitId, $period, [$userId], $professionId);
                    $data = $out['users'][$userId] ?? null;
                }

                if (is_array($data)) {
                    $data['calculation_source'] = $out['calculation_source'] ?? 'live';
                }
            }
        }

        return view('pegawai_medis.my_performance.index', [
            'period' => $period,
            'data' => $data,
            'scope' => $scope,
        ]);
    }

    /**
     * @return array<int>
     */
    private function resolveGroupUserIds(int $unitId, ?int $professionId): array
    {
        if ($unitId <= 0) {
            return [];
        }

        return User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId)
            ->when($professionId === null, fn($q) => $q->whereNull('profession_id'))
            ->when($professionId !== null, fn($q) => $q->where('profession_id', (int) $professionId))
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();
    }
}
