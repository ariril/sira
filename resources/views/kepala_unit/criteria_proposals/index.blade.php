<x-app-layout title="Usulan Kriteria Baru">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Usulan Kriteria Baru</h1>
    </x-slot>

    <div class="container-px py-6 space-y-8">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <h3 class="text-slate-800 font-semibold mb-4">Buat Usulan</h3>
            <form method="POST" action="{{ route('kepala_unit.criteria_proposals.store') }}" class="grid md:grid-cols-12 gap-4 items-end">
                @csrf
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nama Kriteria</label>
                    <x-ui.input name="name" type="text" required placeholder="Nama kriteria" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Saran Bobot (%)</label>
                    <x-ui.input name="suggested_weight" type="number" step="0.01" min="0" max="100" placeholder="Opsional" />
                </div>
                <div class="md:col-span-12">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                    <x-ui.textarea name="description" rows="3" placeholder="Jelaskan manfaat kriteria" />
                </div>
                <div class="md:col-span-3">
                    <x-ui.button type="submit" variant="orange" class="h-11 w-full">Kirim Usulan</x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <h3 class="text-slate-800 font-semibold mb-4">Daftar Usulan Saya</h3>
            <x-ui.table>
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Nama</th>
                        <th class="px-6 py-4 text-left">Saran Bobot</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Dibuat</th>
                    </tr>
                </x-slot>
                @forelse($items as $it)
                    @php($st = $it->status->value)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $it->name }}</td>
                        <td class="px-6 py-3">{{ $it->suggested_weight ? number_format($it->suggested_weight,2).'%' : '-' }}</td>
                        <td class="px-6 py-3">
                            @if($st==='published')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Published</span>
                            @elseif($st==='proposed')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Proposed</span>
                            @elseif($st==='rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-slate-600">{{ $it->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada usulan.</td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
