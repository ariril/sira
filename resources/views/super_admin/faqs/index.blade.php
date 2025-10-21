<x-app-layout title="FAQs">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">FAQs</h1>
            <x-ui.button as="a" href="{{ route('super_admin.faqs.create') }}" variant="primary" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah FAQ
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Pertanyaan / jawaban" addonLeft="fa-magnifying-glass" value="{{ request('q') }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kategori</label>
                    <x-ui.input name="category" value="{{ request('category') }}" placeholder="opsional" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Aktif?</label>
                    <x-ui.select name="active" :options="['yes'=>'Ya','no'=>'Tidak']" :value="request('active')" placeholder="(Semua)"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page" :options="[10=>'10 / halaman',12=>'12 / halaman',20=>'20 / halaman',30=>'30 / halaman',50=>'50 / halaman']" :value="(int)request('per_page', 12)"/>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('super_admin.faqs.index') }}"
                   class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i>
                    Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-6 py-4 text-left">Pertanyaan</th>
                    <th class="px-6 py-4 text-left">Kategori</th>
                    <th class="px-6 py-4 text-left">Urutan</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                @forelse(($faqs ?? collect()) as $f)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-800">{{ $f->question }}</div>
                            <div class="text-xs text-slate-500 line-clamp-1">{{ strip_tags($f->answer) }}</div>
                        </td>
                        <td class="px-6 py-4">{{ $f->category ?: '-' }}</td>
                        <td class="px-6 py-4">{{ $f->order ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($f->is_active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-50 text-slate-700 border border-slate-100">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <x-ui.icon-button as="a" href="{{ route('super_admin.faqs.edit',$f) }}" icon="fa-pen-to-square" />
                                <form action="{{ route('super_admin.faqs.destroy',$f) }}" method="POST" onsubmit="return confirm('Hapus FAQ ini?')">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" variant="danger"/>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- FOOTER PAGINATION --}}
        @if(isset($faqs))
            <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-sm text-slate-600">
                    Menampilkan
                    <span class="font-medium text-slate-800">{{ $faqs->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-medium text-slate-800">{{ $faqs->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-medium text-slate-800">{{ $faqs->total() }}</span>
                    data
                </div>
                <div>
                    {{ $faqs->links() }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
