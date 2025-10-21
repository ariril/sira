<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Enums\PerformanceCriteriaType;
use App\Http\Controllers\Controller;
use App\Models\PerformanceCriteria;
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
        ]);
    }

    public function create(): View
    {
        return view('admin_rs.performance_criterias.create', [
            'types' => $this->types(),
            'item'  => new PerformanceCriteria([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        PerformanceCriteria::create($data);
        return redirect()->route('admin_rs.performance-criterias.index')
            ->with('status', 'Kriteria berhasil dibuat.');
    }

    public function edit(PerformanceCriteria $performance_criteria): View
    {
        return view('admin_rs.performance_criterias.edit', [
            'types' => $this->types(),
            'item'  => $performance_criteria,
        ]);
    }

    public function update(Request $request, PerformanceCriteria $performance_criteria): RedirectResponse
    {
        $data = $this->validateData($request, isUpdate: true);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $performance_criteria->update($data);
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
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }
}
