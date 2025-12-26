<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Profession;
use App\Models\RaterWeight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaterWeightController extends Controller
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
            'assessee_profession_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_map(fn($e) => $e->value, RaterWeightStatus::cases()))],
        ]);

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();
        $professions = Profession::orderBy('name')->get(['id', 'name']);

        $items = RaterWeight::query()
            ->with(['period:id,name', 'assesseeProfession:id,name', 'proposedBy:id,name', 'decidedBy:id,name'])
            ->when(!empty($filters['assessment_period_id'] ?? null), fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id']))
            ->when(!empty($filters['assessee_profession_id'] ?? null), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']))
            ->when(!empty($filters['status'] ?? null), fn($q) => $q->where('status', (string) $filters['status']))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totals = null;
        $periodId = $filters['assessment_period_id'] ?? null;
        $professionId = $filters['assessee_profession_id'] ?? null;
        if (!empty($periodId) && !empty($professionId)) {
            $baseTotalsQuery = RaterWeight::query()
                ->where('assessment_period_id', (int) $periodId)
                ->where('assessee_profession_id', (int) $professionId);

            $totals = [
                'draft_total' => (float) (clone $baseTotalsQuery)
                    ->whereIn('status', [RaterWeightStatus::DRAFT, RaterWeightStatus::REJECTED])
                    ->sum('weight'),
                'pending_total' => (float) (clone $baseTotalsQuery)
                    ->where('status', RaterWeightStatus::PENDING)
                    ->sum('weight'),
                'active_total' => (float) (clone $baseTotalsQuery)
                    ->where('status', RaterWeightStatus::ACTIVE)
                    ->sum('weight'),
            ];
        }

        return view('kepala_unit.rater_weights.index', [
            'items' => $items,
            'periods' => $periods,
            'professions' => $professions,
            'assessorTypes' => self::ASSESSOR_TYPES,
            'statuses' => array_combine(
                array_map(fn($e) => $e->value, RaterWeightStatus::cases()),
                array_map(fn($e) => ucfirst($e->value), RaterWeightStatus::cases()),
            ),
            'filters' => $filters,
            'totals' => $totals,
        ]);
    }

    public function create(): View
    {
        return view('kepala_unit.rater_weights.create', [
            'item' => new RaterWeight(['status' => RaterWeightStatus::DRAFT]),
            'periodOptions' => AssessmentPeriod::orderByDesc('start_date')->pluck('name', 'id'),
            'professionOptions' => Profession::orderBy('name')->pluck('name', 'id'),
            'assessorTypes' => self::ASSESSOR_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['status'] = RaterWeightStatus::DRAFT->value;
        $data['proposed_by'] = null;
        $data['decided_by'] = null;
        $data['decided_at'] = null;

        RaterWeight::create($data);

        return redirect()->route('kepala_unit.rater_weights.index')
            ->with('status', 'Bobot penilai 360 berhasil dibuat sebagai draft.');
    }

    public function edit(RaterWeight $raterWeight): View
    {
        $this->authorizeDraftOrRejected($raterWeight);

        return view('kepala_unit.rater_weights.edit', [
            'item' => $raterWeight,
            'periodOptions' => AssessmentPeriod::orderByDesc('start_date')->pluck('name', 'id'),
            'professionOptions' => Profession::orderBy('name')->pluck('name', 'id'),
            'assessorTypes' => self::ASSESSOR_TYPES,
        ]);
    }

    public function update(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        $this->authorizeDraftOrRejected($raterWeight);

        $data = $this->validatePayload($request, $raterWeight);

        // Any edit resets workflow back to draft.
        $data['status'] = RaterWeightStatus::DRAFT->value;
        $data['proposed_by'] = null;
        $data['decided_by'] = null;
        $data['decided_at'] = null;

        $raterWeight->update($data);

        return redirect()->route('kepala_unit.rater_weights.index')
            ->with('status', 'Bobot penilai 360 berhasil diperbarui (draft).');
    }

    public function submit(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::DRAFT || $raterWeight->status === RaterWeightStatus::REJECTED, 403);

        $raterWeight->status = RaterWeightStatus::PENDING;
        $raterWeight->proposed_by = auth()->id();
        $raterWeight->decided_by = null;
        $raterWeight->decided_at = null;
        $raterWeight->save();

        return back()->with('status', 'Bobot penilai 360 berhasil diajukan (pending).');
    }

    public function destroy(RaterWeight $raterWeight): RedirectResponse
    {
        abort_unless($raterWeight->status === RaterWeightStatus::DRAFT, 403);

        $raterWeight->delete();

        return back()->with('status', 'Draft bobot penilai 360 berhasil dihapus.');
    }

    private function validatePayload(Request $request, ?RaterWeight $existing = null): array
    {
        $types = array_keys(self::ASSESSOR_TYPES);

        return $request->validate([
            'assessment_period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
            'assessee_profession_id' => ['required', 'integer', 'exists:professions,id'],
            'assessor_type' => [
                'required',
                Rule::in($types),
                Rule::unique('rater_weights', 'assessor_type')
                    ->where(fn($q) => $q
                        ->where('assessment_period_id', (int) $request->input('assessment_period_id'))
                        ->where('assessee_profession_id', (int) $request->input('assessee_profession_id'))
                    )
                    ->when($existing, fn($rule) => $rule->ignore($existing->id)),
            ],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function authorizeDraftOrRejected(RaterWeight $raterWeight): void
    {
        abort_unless(
            $raterWeight->status === RaterWeightStatus::DRAFT || $raterWeight->status === RaterWeightStatus::REJECTED,
            403
        );
    }
}
