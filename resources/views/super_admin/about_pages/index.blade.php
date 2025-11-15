<x-app-layout title="Halaman Tentang">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Halaman Tentang</h1>
            <x-ui.button as="a" href="{{ route('super_admin.about-pages.create') }}" variant="primary" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <x-ui.table min-width="880px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Judul</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Publish</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse(($items ?? collect()) as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ \Illuminate\Support\Str::headline(str_replace('_',' ', $p->type?->value ?? $p->type)) }}</td>
                    <td class="px-6 py-4">{{ $p->title ?: '-' }}</td>
                    <td class="px-6 py-4">{{ optional($p->published_at)->format('d M Y H:i') ?: '-' }}</td>
                    <td class="px-6 py-4">
                        @if($p->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-50 text-slate-700 border border-slate-100">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.icon-button as="a" href="{{ route('super_admin.about-pages.edit', $p) }}" icon="fa-pen-to-square" />
                            <form action="{{ route('super_admin.about-pages.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus data ini?')">
                                @csrf @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" variant="danger"/>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</x-app-layout>
