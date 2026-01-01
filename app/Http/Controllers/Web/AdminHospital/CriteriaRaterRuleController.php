<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaRaterRule;
use App\Models\PerformanceCriteria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CriteriaRaterRuleController extends Controller
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
            'q' => ['nullable', 'string', 'max:150'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessor_type' => ['nullable', Rule::in(array_keys(self::ASSESSOR_TYPES))],
        ]);

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q === '') {
            unset($filters['q']);
        } else {
            $filters['q'] = $q;
        }

        $criteriaOptions = PerformanceCriteria::query()
            ->where('is_360', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        $items = CriteriaRaterRule::query()
            ->with('performanceCriteria:id,name')
            ->when(!empty($filters['q'] ?? null), function ($query) use ($q) {
                $query->whereHas('performanceCriteria', function ($criteriaQuery) use ($q) {
                    $criteriaQuery->where('name', 'like', "%{$q}%");

                    if (Schema::hasColumn('performance_criterias', 'description')) {
                        $criteriaQuery->orWhere('description', 'like', "%{$q}%");
                    }
                });
            })
            ->when(!empty($filters['performance_criteria_id'] ?? null), fn($q) => $q->where('performance_criteria_id', (int) $filters['performance_criteria_id']))
            ->when(!empty($filters['assessor_type'] ?? null), fn($q) => $q->where('assessor_type', (string) $filters['assessor_type']))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin_rs.criteria_rater_rules.index', [
            'items' => $items,
            'criteriaOptions' => $criteriaOptions,
            'assessorTypes' => self::ASSESSOR_TYPES,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.criteria_rater_rules.create', [
            'item' => new CriteriaRaterRule(),
            'criteriaOptions' => PerformanceCriteria::query()
                ->where('is_360', true)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'assessorTypes' => self::ASSESSOR_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        CriteriaRaterRule::create($data);

        return redirect()->route('admin_rs.criteria_rater_rules.index')
            ->with('status', 'Aturan kriteria 360 berhasil dibuat.');
    }

    public function edit(CriteriaRaterRule $criteria_rater_rule): View
    {
        $criteria_rater_rule->loadMissing('performanceCriteria:id,name');

        return view('admin_rs.criteria_rater_rules.edit', [
            'item' => $criteria_rater_rule,
            'criteriaOptions' => PerformanceCriteria::query()
                ->where('is_360', true)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'assessorTypes' => self::ASSESSOR_TYPES,
        ]);
    }

    public function update(Request $request, CriteriaRaterRule $criteria_rater_rule): RedirectResponse
    {
        $data = $this->validatePayload($request, $criteria_rater_rule);
        $criteria_rater_rule->update($data);

        return redirect()->route('admin_rs.criteria_rater_rules.index')
            ->with('status', 'Aturan kriteria 360 berhasil diperbarui.');
    }

    public function destroy(CriteriaRaterRule $criteria_rater_rule): RedirectResponse
    {
        $criteria_rater_rule->delete();

        return back()->with('status', 'Aturan kriteria 360 berhasil dihapus.');
    }

    private function validatePayload(Request $request, ?CriteriaRaterRule $existing = null): array
    {
        $types = array_keys(self::ASSESSOR_TYPES);

        return $request->validate([
            'performance_criteria_id' => [
                'required',
                'integer',
                Rule::exists('performance_criterias', 'id')->where(fn($q) => $q->where('is_360', true)),
            ],
            'assessor_type' => [
                'required',
                Rule::in($types),
                Rule::unique('criteria_rater_rules', 'assessor_type')
                    ->where(fn($q) => $q->where('performance_criteria_id', (int) $request->input('performance_criteria_id')))
                    ->when($existing, fn($rule) => $rule->ignore($existing->id)),
            ],
        ]);
    }
}
