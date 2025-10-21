@php /** @var \App\Models\User $user */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nama *</label>
            <x-ui.input name="name" :value="old('name', $user->name)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email *</label>
            <x-ui.input type="email" name="email" :value="old('email', $user->email)" required />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">NIP / Employee Number</label>
            <x-ui.input name="employee_number" :value="old('employee_number', $user->employee_number)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Role *</label>
            <x-ui.select name="role"
                         :options="$roles"
                         :value="old('role', $user->role)"
                         required
                         placeholder="Pilih role" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Jabatan/Posisi</label>
            <x-ui.input name="position" :value="old('position', $user->position)" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Mulai Bekerja</label>
                <x-ui.input type="date" name="start_date" :value="old('start_date', $user->start_date)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">No. HP</label>
                <x-ui.input name="phone" :value="old('phone', $user->phone)" />
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Unit</label>
            <x-ui.select name="unit_id"
                         :options="$units->pluck('name','id')"
                         :value="old('unit_id', $user->unit_id)"
                         placeholder="Pilih unit" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Profesi</label>
            <x-ui.select name="profession_id"
                         :options="$professions->pluck('name','id')"
                         :value="old('profession_id', $user->profession_id)"
                         placeholder="Pilih profesi" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Jenis Kelamin</label>
                <x-ui.input name="gender" :value="old('gender', $user->gender)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Kewarganegaraan</label>
                <x-ui.input name="nationality" :value="old('nationality', $user->nationality)" />
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Pendidikan Terakhir</label>
            <x-ui.input name="last_education" :value="old('last_education', $user->last_education)" />
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Alamat</label>
            <x-ui.textarea name="address" rows="3" :value="old('address', $user->address)" />
        </div>
    </div>

    <div class="md:col-span-2 grid md:grid-cols-2 gap-4 items-start">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
                {{ $user->exists ? 'Password (opsional, biarkan kosong)' : 'Password *' }}
            </label>
            <x-ui.input type="password" name="password" />
        </div>

        <div class="flex items-center gap-6 mt-6 md:mt-0">
            @if(!$user->email_verified_at)
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="verify_now" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600">
                    <span class="text-sm text-slate-700">Tandai email terverifikasi</span>
                </label>
            @else
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="unverify_now" value="1" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                    <span class="text-sm text-slate-700">Cabut verifikasi email</span>
                </label>
            @endif
        </div>
    </div>

    <div class="md:col-span-2 flex items-center justify-between pt-2">
        <x-ui.button as="a" href="{{ route('super_admin.users.index') }}" variant="outline">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </x-ui.button>

        <x-ui.button type="submit" variant="{{ $user->exists ? 'success' : 'primary' }}">
            <i class="fa-solid fa-floppy-disk"></i>
            {{ $user->exists ? 'Update' : 'Create' }}
        </x-ui.button>
    </div>
</div>
