<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $types = [
            'manajemen'   => 'Manajemen',
            'admin_rs'    => 'Admin RS',
            'penunjang'   => 'Penunjang',
            'rawat_inap'  => 'Rawat Inap',
            'igd'         => 'IGD',
            'poliklinik'  => 'Poliklinik',
            'lainnya'     => 'Lainnya',
        ];

        $perPageOptions = [5, 10, 12, 20, 30, 50];

        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'type'     => ['nullable','in:'.implode(',', array_keys($types))],
            'status'   => ['nullable','in:yes,no'],
            'per_page' => ['nullable','integer','in:'.implode(',', $perPageOptions)],
        ]);

        $q        = $data['q']        ?? null;
        $type     = $data['type']     ?? null;
        $status   = $data['status']   ?? null;
        $perPage  = (int)($data['per_page'] ?? 12);

        $units = Unit::query()
            ->when($q, function($w) use ($q) {
                $w->where(function($x) use ($q) {
                    $x->where('name','like',"%{$q}%")
                        ->orWhere('slug','like',"%{$q}%")
                        ->orWhere('code','like',"%{$q}%")
                        ->orWhere('email','like',"%{$q}%")
                        ->orWhere('phone','like',"%{$q}%");
                });
            })
            ->when($type, fn($w) => $w->where('type', $type))
            ->when($status === 'yes', fn($w) => $w->where('is_active', true))
            ->when($status === 'no',  fn($w) => $w->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.units.index', [
            'units'          => $units,
            'types'          => $types,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'filters'        => compact('q','type','status'),
        ]);
    }

    public function create(): View
    {
        return view('super_admin.units.create', [
            'unit'  => new Unit(),
            'types' => [
                'manajemen'   => 'Manajemen',
                'admin_rs'    => 'Admin RS',
                'penunjang'   => 'Penunjang',
                'rawat_inap'  => 'Rawat Inap',
                'igd'         => 'IGD',
                'poliklinik'  => 'Poliklinik',
                'lainnya'     => 'Lainnya',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required','string','max:255'],
            'slug'               => ['required','string','max:255','unique:units,slug'],
            'code'               => ['nullable','string','max:20'],
            'type'               => ['required', Rule::in(['manajemen','admin_rs','penunjang','rawat_inap','igd','poliklinik','lainnya'])],
            'parent_id'          => ['nullable','exists:units,id'],
            'location'           => ['nullable','string','max:255'],
            'phone'              => ['nullable','string','max:30'],
            'email'              => ['nullable','email','max:150'],
            'remuneration_ratio' => ['nullable','numeric','between:0,999.99'],
            'is_active'          => ['nullable','boolean'],
        ]);

        $validated['is_active'] = (bool)($request->boolean('is_active'));

        Unit::create($validated);

        return redirect()->route('super_admin.units.index')
            ->with('status', 'Unit berhasil dibuat.');
    }

    public function edit(Unit $unit): View
    {
        return view('super_admin.units.edit', [
            'unit'  => $unit,
            'types' => [
                'manajemen'   => 'Manajemen',
                'admin_rs'    => 'Admin RS',
                'penunjang'   => 'Penunjang',
                'rawat_inap'  => 'Rawat Inap',
                'igd'         => 'IGD',
                'poliklinik'  => 'Poliklinik',
                'lainnya'     => 'Lainnya',
            ],
        ]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required','string','max:255'],
            'slug'               => ['required','string','max:255','unique:units,slug,'.$unit->id],
            'code'               => ['nullable','string','max:20'],
            'type'               => ['required', Rule::in(['manajemen','admin_rs','penunjang','rawat_inap','igd','poliklinik','lainnya'])],
            'parent_id'          => ['nullable','exists:units,id'],
            'location'           => ['nullable','string','max:255'],
            'phone'              => ['nullable','string','max:30'],
            'email'              => ['nullable','email','max:150'],
            'remuneration_ratio' => ['nullable','numeric','between:0,999.99'],
            'is_active'          => ['nullable','boolean'],
        ]);

        $validated['is_active'] = (bool)($request->boolean('is_active'));

        $unit->update($validated);

        return redirect()->route('super_admin.units.index')
            ->with('status', 'Unit berhasil diperbarui.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();
        return back()->with('status', 'Unit dihapus.');
    }
}
