<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        // Opsi role untuk filter
        $roles = [
            'super_admin'       => 'Super Admin',
            'admin_rs'          => 'Admin RS',
            'kepala_poliklinik' => 'Kepala Poliklinik',
            'kepala_unit'       => 'Kepala Unit',
            'pegawai_medis'     => 'Pegawai Medis',
        ];

        // Opsi per page yang diizinkan
        $perPageOptions = [5, 10, 12, 20, 30, 50];

        // Validasi input GET
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'role'     => ['nullable', 'in:' . implode(',', array_keys($roles))],
            'unit'     => ['nullable', 'integer', 'exists:units,id'],
            'verified' => ['nullable', 'in:yes,no'], // kosong = semua
            'per_page' => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
        ]);

        $q        = $data['q']        ?? null;
        $role     = $data['role']     ?? null;
        $unitId   = $data['unit']     ?? null;
        $verified = $data['verified'] ?? null;
        $perPage  = (int)($data['per_page'] ?? 12);

        $users = User::query()
            ->with(['unit:id,name', 'profession:id,name'])
            ->when($q, function ($qBuilder) use ($q) {
                $qBuilder->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('employee_number', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role, fn ($w) => $w->where('role', $role))
            ->when($unitId, fn ($w) => $w->where('unit_id', $unitId))
            ->when($verified === 'yes', fn ($w) => $w->whereNotNull('email_verified_at'))
            ->when($verified === 'no',  fn ($w) => $w->whereNull('email_verified_at'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.users.index', [
            'users'           => $users,
            'roles'           => $roles,
            'units'           => Unit::orderBy('name')->get(['id', 'name']),
            'professions'     => Profession::orderBy('name')->get(['id', 'name']),
            'perPage'         => $perPage,
            'perPageOptions'  => $perPageOptions,
            'filters'         => [
                'q'        => $q,
                'role'     => $role,
                'unit'     => $unitId,
                'verified' => $verified,
            ],
        ]);
    }

    /** Form create */
    public function create(): View
    {
        return view('super_admin.users.create', [
            'roles'       => $this->roles(),
            'units'       => Unit::orderBy('name')->get(['id', 'name']),
            'professions' => Profession::orderBy('name')->get(['id', 'name']),
            'user'        => new User(), // default binding form
        ]);
    }

    /** Simpan user baru */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, isCreate: true);

        // set password
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // optional: langsung verifikasi jika diminta
        if ($request->boolean('verify_now')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()
            ->route('super_admin.users.index')
            ->with('status', 'User berhasil dibuat.');
    }

    /** Form edit */
    public function edit(User $user): View
    {
        return view('super_admin.users.edit', [
            'user'        => $user,
            'roles'       => $this->roles(),
            'units'       => Unit::orderBy('name')->get(['id', 'name']),
            'professions' => Profession::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Update user */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateData($request, isCreate: false, userId: $user->id);

        // password opsional saat edit
        if (!empty($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        // atur verifikasi email sesuai input toggle
        if ($request->has('verify_now')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        } elseif ($request->has('unverify_now')) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        return redirect()
            ->route('super_admin.users.index')
            ->with('status', 'User berhasil diperbarui.');
    }

    /** Hapus user */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('status', 'User dihapus.');
    }

    // ===== Helpers =====

    protected function roles(): array
    {
        return [
            'super_admin'       => 'Super Admin',
            'admin_rs'          => 'Admin RS',
            'kepala_poliklinik' => 'Kepala Poliklinik',
            'kepala_unit'       => 'Kepala Unit',
            'pegawai_medis'     => 'Pegawai Medis',
        ];
    }

    protected function validateData(Request $request, bool $isCreate = true, ?int $userId = null): array
    {
        $emailRule = Rule::unique('users', 'email');
        $empRule   = Rule::unique('users', 'employee_number');

        if (!$isCreate && $userId) {
            $emailRule = $emailRule->ignore($userId);
            $empRule   = $empRule->ignore($userId);
        }

        return $request->validate([
            'employee_number' => ['nullable', 'string', 'max:255', $empRule],
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', $emailRule],
            'role'            => ['required', Rule::in(array_keys($this->roles()))],
            'unit_id'         => ['nullable', 'exists:units,id'],
            'profession_id'   => ['nullable', 'exists:professions,id'],
            'position'        => ['nullable', 'string', 'max:255'],
            'start_date'      => ['nullable', 'date'],
            'gender'          => ['nullable', 'string', 'max:10'],
            'nationality'     => ['nullable', 'string', 'max:50'],
            'address'         => ['nullable', 'string'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'last_education'  => ['nullable', 'string', 'max:50'],

            // password rules
            'password' => $isCreate
                ? ['required', 'string', 'min:6']
                : ['nullable', 'string', 'min:6'],
        ]);
    }
}
