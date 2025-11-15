<x-app-layout title="Approval Usulan Kriteria">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Approval Usulan Kriteria</h1>
    </x-slot>

    <div class="container-px py-6">
        <x-ui.table>
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Nama</th>
                    <th class="px-6 py-4 text-left">Saran Bobot</th>
                    <th class="px-6 py-4 text-left">Diusulkan Oleh</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3">{{ $it->name }}</td>
                    <td class="px-6 py-3">{{ $it->suggested_weight ? number_format($it->suggested_weight,2).'%' : '-' }}</td>
                    <td class="px-6 py-3">{{ $it->unitHead?->name ?? 'Kepala Unit' }}</td>
                    <td class="px-6 py-3 text-right">
                        <div class="inline-flex gap-2">
                            <form method="POST" action="{{ route('admin_rs.criteria_proposals.approve', $it->id) }}" onsubmit="return confirm('Setujui usulan ini?')">
                                @csrf
                                <x-ui.button type="submit" variant="success" class="h-9 px-4 text-sm">Setujui</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('admin_rs.criteria_proposals.reject', $it->id) }}" onsubmit="return confirm('Tolak usulan ini?')">
                                @csrf
                                <x-ui.button type="submit" variant="danger" class="h-9 px-4 text-sm">Tolak</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Tidak ada usulan.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</x-app-layout>
