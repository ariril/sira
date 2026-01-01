@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit
        ? route('admin.master.profession_hierarchy.update', $item)
        : route('admin.master.profession_hierarchy.store');

    $assesseeProfessions = $professions;
    if (!$isEdit) {
        $assesseeProfessions = $professions->reject(function ($p) {
            $name = mb_strtolower((string) ($p->name ?? ''));
            return str_contains($name, 'kepala unit')
                || str_contains($name, 'kepala poli')
                || str_contains($name, 'kepala poliklinik');
        });
    }
@endphp

<x-app-layout title="Hirarki Penilai Profesi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">
                {{ $isEdit ? 'Edit Hirarki Penilai Profesi' : 'Tambah Hirarki Penilai Profesi' }}
            </h1>
            <x-ui.button as="a" href="{{ route('admin.master.profession_hierarchy.index') }}" variant="outline" class="h-12 px-6 text-base">
                <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if(session('status'))
            <div class="bg-emerald-50 text-emerald-800 border border-emerald-100 rounded-xl p-4">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-rose-50 text-rose-800 border border-rose-100 rounded-xl p-4">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100" id="hierarchyForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Assessee Profesi</label>
                    <x-ui.select
                        name="assessee_profession_id"
                        :options="$assesseeProfessions->pluck('name','id')->all()"
                        :value="old('assessee_profession_id', $item->assessee_profession_id)"
                        placeholder="Pilih profesi" />
                </div>

                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Relasi</label>
                    <x-ui.select
                        name="relation_type"
                        :options="$relationTypes"
                        :value="old('relation_type', $item->relation_type)"
                        placeholder="Pilih relasi" 
                        id="relation_type" />
                </div>

                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Assessor Profesi</label>
                    <x-ui.select
                        name="assessor_profession_id"
                        :options="$professions->pluck('name','id')->all()"
                        :value="old('assessor_profession_id', $item->assessor_profession_id)"
                        placeholder="Pilih profesi" />
                </div>

                <div class="md:col-span-6" id="levelWrap">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Level (khusus Supervisor)</label>
                    <x-ui.input name="level" type="number" min="1" placeholder="Contoh: 1" value="{{ old('level', $item->level) }}" id="level" />
                    <div class="text-xs text-slate-500 mt-2">Wajib diisi jika relasi Supervisor. Kosong untuk Peer/Subordinate.</div>
                </div>

                <div class="md:col-span-6">
                    <label class="inline-flex items-center gap-3">
                        <input type="checkbox" name="is_required" value="1" class="rounded border-slate-300"
                            {{ old('is_required', $item->is_required) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700">Required</span>
                    </label>
                </div>

                <div class="md:col-span-6">
                    <label class="inline-flex items-center gap-3">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300"
                            {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                        <span class="text-sm text-slate-700">Aktif</span>
                    </label>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
                <x-ui.button type="submit" variant="success" class="h-12 px-8 text-base">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Simpan
                </x-ui.button>
            </div>
        </form>
    </div>

    <script>
        (function(){
            const rel = document.getElementById('relation_type');
            const wrap = document.getElementById('levelWrap');
            const level = document.getElementById('level');

            function sync(){
                const v = (rel && rel.value) ? rel.value : 'supervisor';
                const isSupervisor = v === 'supervisor';

                if (wrap) wrap.style.display = isSupervisor ? '' : 'none';

                // Prevent prohibited_unless from triggering when not supervisor
                if (level) {
                    if (isSupervisor) {
                        level.disabled = false;
                    } else {
                        level.value = '';
                        level.disabled = true;
                    }
                }
            }

            if (rel) rel.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
