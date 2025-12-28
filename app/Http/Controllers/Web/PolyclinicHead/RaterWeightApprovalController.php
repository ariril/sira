<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Models\Profession;
use App\Models\RaterWeight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaterWeightApprovalController extends Controller
{
    private const ASSESSOR_TYPES = [
        'self' => 'Diri sendiri',
        'supervisor' => 'Atasan',
        'peer' => 'Rekan',
        'subordinate' => 'Bawahan',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'rejected', 'archived', 'all'])],
        ]);

        $status = $filters['status'] ?? 'pending';

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();

        $me = Auth::user();
        $scopeUnitIds = collect();

        if (Schema::hasTable('units') && $me?->unit_id) {
            $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        $professionIds = collect();
        if ($scopeUnitIds->isNotEmpty() && Schema::hasTable('users')) {
            $professionIds = DB::table('users')
                ->whereIn('unit_id', $scopeUnitIds)
                ->whereNotNull('profession_id')
                ->distinct()
                ->pluck('profession_id');
        }

        $professions = Profession::query()
            ->when($professionIds->isNotEmpty(), fn($q) => $q->whereIn('id', $professionIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = RaterWeight::query()
            ->with(['period:id,name', 'unit:id,name', 'criteria:id,name', 'assesseeProfession:id,name', 'assessorProfession:id,name', 'proposedBy:id,name', 'decidedBy:id,name'])
            ->when($scopeUnitIds->isNotEmpty(), fn($q) => $q->whereIn('unit_id', $scopeUnitIds))
            ->when($professionIds->isNotEmpty(), fn($q) => $q->whereIn('assessee_profession_id', $professionIds))
            ->when(!empty($filters['assessment_period_id'] ?? null), fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id']))
            ->when(!empty($filters['performance_criteria_id'] ?? null), fn($q) => $q->where('performance_criteria_id', (int) $filters['performance_criteria_id']))
            ->when(!empty($filters['assessee_profession_id'] ?? null), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']));

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $pendingCount = (clone $query)->where('status', RaterWeightStatus::PENDING->value)->count();

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $criteriaOptions = PerformanceCriteria::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('kepala_poli.rater_weights.index', [
            'items' => $items,
            'periods' => $periods,
            'criteriaOptions' => $criteriaOptions,
            'professions' => $professions,
            'assessorTypes' => self::ASSESSOR_TYPES,
            'filters' => [
                ...$filters,
                'status' => $status,
            ],
            'pendingCount' => $pendingCount,
        ]);
    }

    public function approve(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        DB::transaction(function () use ($raterWeight) {
            // Archive existing active weights for the same key.
            RaterWeight::query()
                ->where('assessment_period_id', $raterWeight->assessment_period_id)
                ->where('unit_id', $raterWeight->unit_id)
                ->where('performance_criteria_id', $raterWeight->performance_criteria_id)
                ->where('assessee_profession_id', $raterWeight->assessee_profession_id)
                ->where('assessor_type', $raterWeight->assessor_type)
                ->where(function ($q) use ($raterWeight) {
                    if ($raterWeight->assessor_profession_id === null) {
                        $q->whereNull('assessor_profession_id');
                    } else {
                        $q->where('assessor_profession_id', $raterWeight->assessor_profession_id);
                    }

                    if ($raterWeight->assessor_level === null) {
                        $q->whereNull('assessor_level');
                    } else {
                        $q->where('assessor_level', $raterWeight->assessor_level);
                    }
                })
                ->where('status', RaterWeightStatus::ACTIVE->value)
                ->update([
                    'status' => RaterWeightStatus::ARCHIVED->value,
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                ]);

            $raterWeight->status = RaterWeightStatus::ACTIVE;
            $raterWeight->decided_by = auth()->id();
            $raterWeight->decided_at = now();
            $raterWeight->save();
        });

        return back()->with('status', 'Bobot penilai 360 disetujui dan diaktifkan.');
    }

    public function reject(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::PENDING, 403);

        $raterWeight->status = RaterWeightStatus::REJECTED;
        $raterWeight->decided_by = auth()->id();
        $raterWeight->decided_at = now();
        $raterWeight->save();

        return back()->with('status', 'Bobot penilai 360 ditolak.');
    }
}
