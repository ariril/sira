@php
    // helper aliases for this view
    use Illuminate\Support\Str;
@endphp

<x-app-layout title="Announcements">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Announcements</h1>
            <x-ui.button as="a" href="{{ route('super_admin.announcements.create') }}" variant="primary" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Pengumuman
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Judul / ringkasan" addonLeft="fa-magnifying-glass" value="{{ request('q') }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kategori</label>
                    <x-ui.select name="category" :options="collect(\App\Enums\AnnouncementCategory::cases())->mapWithKeys(fn($c)=>[$c->value=>Str::headline($c->value)])->all()" :value="request('category')" placeholder="Semua"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Label</label>
                    <x-ui.select name="label" :options="collect(\App\Enums\AnnouncementLabel::cases())->mapWithKeys(fn($c)=>[$c->value=>Str::headline($c->value)])->all()" :value="request('label')" placeholder="Semua"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['draft'=>'Draft','scheduled'=>'Terjadwal','published'=>'Terpublikasi','expired'=>'Kedaluwarsa']" :value="request('status')" placeholder="Semua"/>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page" :options="[10=>'10 / halaman',12=>'12 / halaman',20=>'20 / halaman',30=>'30 / halaman',50=>'50 / halaman']" :value="(int)request('per_page', 12)"/>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('super_admin.announcements.index') }}"
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
                    <th class="px-6 py-4 text-left">Judul</th>
                    <th class="px-6 py-4 text-left">Kategori</th>
                    <th class="px-6 py-4 text-left">Label</th>
                    <th class="px-6 py-4 text-left">Publikasi</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                @forelse(($announcements ?? collect()) as $a)
                    @php
                        $now = \Illuminate\Support\Carbon::now();
                        $status = 'Draft';
                        $badge = 'bg-slate-50 text-slate-700 border-slate-100';
                        if($a->published_at){
                            if($a->published_at->isFuture()) { $status = 'Terjadwal'; $badge='bg-amber-50 text-amber-700 border-amber-100'; }
                            else {
                                if($a->expired_at && $a->expired_at->lt($now)) { $status='Kedaluwarsa'; $badge='bg-rose-50 text-rose-700 border-rose-100'; }
                                else { $status='Terpublikasi'; $badge='bg-emerald-50 text-emerald-700 border-emerald-100'; }
                            }
                        }
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-800">{{ $a->title }}</div>
                            <div class="text-xs text-slate-500">/{{ $a->slug }}</div>
                        </td>
                        <td class="px-6 py-4">{{ \Illuminate\Support\Str::headline($a->category?->value ?? '-') }}</td>
                        <td class="px-6 py-4">{{ \Illuminate\Support\Str::headline($a->label?->value ?? '-') }}</td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-slate-600">
                                @if($a->published_at)
                                    <div>Mulai: <span class="font-medium text-slate-800">{{ $a->published_at->format('d M Y H:i') }}</span></div>
                                @else
                                    <div class="text-slate-500">-</div>
                                @endif
                                @if($a->expired_at)
                                    <div>Berakhir: <span class="font-medium text-slate-800">{{ $a->expired_at->format('d M Y H:i') }}</span></div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $badge }}">{{ $status }}</span>
                            @if($a->is_featured)
                                <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    <i class="fa-solid fa-star"></i> Featured
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <x-ui.icon-button as="a" href="{{ route('super_admin.announcements.edit',$a) }}" icon="fa-pen-to-square" />
                                <form action="{{ route('super_admin.announcements.destroy',$a) }}" method="POST" onsubmit="return confirm('Hapus pengumuman ini?')">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" variant="danger"/>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- FOOTER PAGINATION --}}
        @if(isset($announcements))
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $announcements->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $announcements->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $announcements->total() }}</span>
                data
            </div>
            <div>
                {{ $announcements->links() }}
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
