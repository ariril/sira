<x-app-layout title="Hirarki Penilai Profesi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Hirarki Penilai Profesi</h1>
            <x-ui.button as="a" href="{{ route('admin.master.profession_hierarchy.create') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Aturan
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Assessee Profesi</label>
                    <x-ui.select
                        name="assessee_profession_id"
                        :options="$professions->pluck('name','id')->all()"
                        :value="$filters['assessee_profession_id'] ?? null"
                        placeholder="(Semua)" />
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Relasi</label>
                    <x-ui.select
                        name="relation_type"
                        :options="$relationTypes"
                        :value="$filters['relation_type'] ?? null"
                        placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select
                        name="is_active"
                        :options="['1'=>'Aktif','0'=>'Nonaktif']"
                        :value="$filters['is_active'] ?? null"
                        placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2 flex items-end justify-end gap-3">
                    <a href="{{ route('admin.master.profession_hierarchy.index') }}"
                       class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                        <i class="fa-solid fa-filter"></i>
                        Terapkan
                    </button>
                </div>
            </div>
        </form>

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

        <div class="space-y-4">
            @forelse($grouped as $assesseeId => $items)
                @php
                    $assesseeName = optional($items->first()->assesseeProfession)->name ?? 'Profesi';
                @endphp

                <details class="bg-white rounded-2xl shadow-sm border border-slate-100" open>
                    <summary class="cursor-pointer select-none px-6 py-4 flex items-center justify-between">
                        <div class="font-semibold text-slate-800">{{ $assesseeName }}</div>
                        <div class="text-sm text-slate-500">{{ $items->count() }} aturan</div>
                    </summary>

                    <div class="px-6 pb-6">
                        <x-ui.table min-width="960px">
                            <x-slot name="head">
                                <tr>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Assessee Profesi</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Relasi</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Level</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Assessor Profesi</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Required?</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                                </tr>
                            </x-slot>

                            @foreach($items as $it)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">{{ $it->assesseeProfession?->name }}</td>
                                    <td class="px-6 py-4">{{ $relationTypes[$it->relation_type] ?? $it->relation_type }}</td>
                                    <td class="px-6 py-4">{{ $it->relation_type === 'supervisor' ? ($it->level ?? '-') : '-' }}</td>
                                    <td class="px-6 py-4">{{ $it->assessorProfession?->name }}</td>
                                    <td class="px-6 py-4">
                                        @if($it->is_required)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Ya</span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">Tidak</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($it->is_active)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('admin.master.profession_hierarchy.edit', $it) }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-medium bg-white border border-slate-200 hover:bg-slate-50">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>

                                        <form method="POST" action="{{ route('admin.master.profession_hierarchy.toggle', $it) }}" class="inline">
                                            @csrf
                                            <button class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-medium bg-white border border-slate-200 hover:bg-slate-50">
                                                <i class="fa-solid {{ $it->is_active ? 'fa-toggle-on text-emerald-600' : 'fa-toggle-off text-slate-500' }}"></i>
                                                {{ $it->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.master.profession_hierarchy.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus aturan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-medium bg-white border border-rose-200 text-rose-700 hover:bg-rose-50">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                </details>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-10 border border-slate-100 text-center text-slate-600">
                    Belum ada aturan hirarki. Klik <span class="font-semibold">Tambah Aturan</span> untuk membuat.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
