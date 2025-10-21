<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Profession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfessionController extends Controller
{
    public function index(Request $request): View
    {
        $perPageOptions = [5, 10, 12, 20, 30, 50];

        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'status'   => ['nullable','in:active,inactive'],
            'per_page' => ['nullable','integer','in:'.implode(',', $perPageOptions)],
        ]);

        $q       = $data['q']       ?? null;
        $status  = $data['status']  ?? null;
        $perPage = (int)($data['per_page'] ?? 12);

        $professions = Profession::query()
            ->when($q, function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active',   fn($w) => $w->where('is_active', true))
            ->when($status === 'inactive', fn($w) => $w->where('is_active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.professions.index', [
            'professions'    => $professions,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function create(): View
    {
        return view('super_admin.professions.create', [
            'profession' => new Profession(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required','string','max:255','unique:professions,name'],
            'code'        => ['nullable','string','max:50','unique:professions,code'],
            'description' => ['nullable','string'],
            'is_active'   => ['sometimes','boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        Profession::create($data);

        return redirect()
            ->route('super_admin.professions.index')
            ->with('status', 'Profession berhasil dibuat.');
    }

    public function edit(Profession $profession): View
    {
        return view('super_admin.professions.edit', [
            'profession' => $profession,
        ]);
    }

    public function update(Request $request, Profession $profession): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required','string','max:255', Rule::unique('professions','name')->ignore($profession->id)],
            'code'        => ['nullable','string','max:50', Rule::unique('professions','code')->ignore($profession->id)],
            'description' => ['nullable','string'],
            'is_active'   => ['sometimes','boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $profession->update($data);

        return redirect()
            ->route('super_admin.professions.index')
            ->with('status', 'Profession berhasil diperbarui.');
    }

    public function destroy(Profession $profession): RedirectResponse
    {
        $profession->delete();

        return back()->with('status', 'Profession dihapus.');
    }
}
