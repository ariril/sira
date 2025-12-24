<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Enums\PerformanceCriteriaType;
use App\Http\Controllers\Controller;
use App\Models\PerformanceCriteria;
use App\Models\CriteriaProposal;
use App\Enums\CriteriaProposalStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PerformanceCriteriaController extends Controller
{
    // Mapping type options for selects
    protected function types(): array
    {
        return [
            \App\Enums\PerformanceCriteriaType::BENEFIT->value => 'Benefit',
            \App\Enums\PerformanceCriteriaType::COST->value    => 'Cost',
        ];
    }

    protected function normalizationBases(): array
    {
        return [
            'total_unit'   => 'Total unit (∑ data seluruh unit)',
            'max_unit'     => 'Nilai maksimum unit',
            'average_unit' => 'Rata-rata unit',
            'custom_target'=> 'Target khusus',
        ];
    }

    // Allowed per-page options (match Super Admin style)
    protected function perPageOptions(): array
    {
        return [5, 10, 12, 20, 30, 50];
    }

    public function index(Request $request): View
    {
        $perPageOptions = $this->perPageOptions();
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'type'     => ['nullable', 'in:' . implode(',', array_keys($this->types()))],
            'active'   => ['nullable', 'in:yes,no'],
            'per_page' => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
        ]);

        $q       = $data['q'] ?? null;
        $type    = $data['type'] ?? null;
        $active  = $data['active'] ?? null; // yes/no
        $perPage = (int)($data['per_page'] ?? 12);

        $items = PerformanceCriteria::query()
            ->when($q, function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($type, fn($w) => $w->where('type', $type))
            ->when($active === 'yes', fn($w) => $w->where('is_active', true))
            ->when($active === 'no',  fn($w) => $w->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Load proposed criteria to be reviewed inline under this page
        $proposals = CriteriaProposal::query()
            ->with(['unitHead:id,name'])
            ->where('status', CriteriaProposalStatus::PROPOSED)
            ->orderBy('id')
            ->get(['id','name','description','suggested_weight','unit_head_id','created_at']);

        return view('admin_rs.performance_criterias.index', [
            'items'           => $items,
            'types'           => $this->types(),
            'perPage'         => $perPage,
            'perPageOptions'  => $perPageOptions,
            'filters'         => [
                'q'      => $q,
                'type'   => $type,
                'active' => $active,
            ],
            'proposals'       => $proposals,
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.performance_criterias.create', [
            'types' => $this->types(),
            'normalizationBases' => $this->normalizationBases(),
            'item'  => new PerformanceCriteria([
                'is_active' => true,
                'normalization_basis' => 'total_unit',
            ]),
            'hasOtherCriteria' => PerformanceCriteria::count() > 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $applyAll = (bool)$request->boolean('apply_basis_to_all');
        $this->ensureNormalizationPolicy($data['normalization_basis'] ?? null, $applyAll);
        $item = PerformanceCriteria::create($data);
        $this->syncRaterWeights($request, $item);
        return redirect()->route('admin_rs.performance-criterias.index')
            ->with('status', 'Kriteria berhasil dibuat.');
    }

    public function edit(PerformanceCriteria $performance_criteria): View
    {
        return view('admin_rs.performance_criterias.edit', [
            'types' => $this->types(),
            'normalizationBases' => $this->normalizationBases(),
            'item'  => $performance_criteria,
            'hasOtherCriteria' => PerformanceCriteria::where('id','!=',$performance_criteria->id)->exists(),
        ]);
    }

    public function update(Request $request, PerformanceCriteria $performance_criteria): RedirectResponse
    {
        $data = $this->validateData($request, isUpdate: true);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $applyAll = (bool)$request->boolean('apply_basis_to_all');
        $this->ensureNormalizationPolicy($data['normalization_basis'] ?? null, $applyAll, $performance_criteria->id);
        $performance_criteria->update($data);
        $this->syncRaterWeights($request, $performance_criteria);
        return redirect()->route('admin_rs.performance-criterias.index')
            ->with('status', 'Kriteria diperbarui.');
    }

    public function destroy(PerformanceCriteria $performance_criteria): RedirectResponse
    {
        // Prevent delete if related records exist
        if ($performance_criteria->unitCriteriaWeights()->exists() || $performance_criteria->assessmentDetails()->exists()) {
            return back()->withErrors(['delete' => 'Tidak dapat menghapus: sudah terpakai pada unit/penilaian.']);
        }
        $performance_criteria->delete();
        return back()->with('status', 'Kriteria dihapus.');
    }

    protected function validateData(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:' . implode(',', array_keys($this->types()))],
            'data_type'   => ['nullable','in:numeric,percentage,boolean,datetime,text'],
            'input_method'=> ['nullable','in:system,manual,import,360,public_review'],
            'aggregation_method' => ['nullable','in:sum,avg,count,latest,custom'],
            'normalization_basis' => ['required','in:total_unit,max_unit,average_unit,custom_target'],
            'custom_target_value' => ['nullable','numeric','min:0','required_if:normalization_basis,custom_target'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
            'suggested_weight' => ['nullable','numeric','min:0','max:100'],
            'apply_basis_to_all' => ['nullable','boolean'],
        ]);
    }

    private function ensureNormalizationPolicy(?string $basis, bool $applyAll, ?int $currentId = null): void
    {
        if (!$basis) { return; }

        $existingBases = PerformanceCriteria::query()
            ->when($currentId, fn($q) => $q->where('id','!=',$currentId))
            ->distinct()
            ->pluck('normalization_basis')
            ->filter()
            ->all();

        $hasConflict = collect($existingBases)->reject(fn($b) => $b === $basis)->isNotEmpty();

        if ($hasConflict && !$applyAll) {
            abort(redirect()->back()->withErrors([
                'normalization_basis' => 'Kebijakan normalisasi harus sama untuk seluruh kriteria. Centang "Terapkan ke semua kriteria" untuk menyamakan.',
            ]));
        }

        if ($hasConflict && $applyAll) {
            PerformanceCriteria::query()->update(['normalization_basis' => $basis]);
        }
    }

    private function syncRaterWeights(\Illuminate\Http\Request $request, \App\Models\PerformanceCriteria $criteria): void
    {
        if ($criteria->input_method !== '360') return;
        $weights = $request->input('rater_weights', []);
        foreach (['supervisor','peer','subordinate','self','patient','other'] as $type) {
            $val = $weights[$type] ?? null;
            if ($val === null || $val === '') { $criteria->raterWeights()->where('assessor_type',$type)->delete(); continue; }
            \App\Models\RaterTypeWeight::updateOrCreate(
                ['performance_criteria_id' => $criteria->id, 'assessor_type' => $type],
                ['weight' => (float)$val]
            );
        }
    }
}
