<x-app-layout title="Periode Penilaian (Admin RS)">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Periode Penilaian</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.assessment-periods.create') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Periode
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
            @if ($errors->any())
                <div class="rounded-lg bg-red-50 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>
            @endif
            {{-- Flash status sudah ditampilkan di layout utama, hindari duplikasi di sini --}}
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama periode" addonLeft="fa-magnifying-glass" value="{{ $filters['q'] ?? '' }}" />
                </div>

                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['draft'=>'Draft','active'=>'Aktif','locked'=>'Dikunci','closed'=>'Ditutup']" :value="$filters['status'] ?? null" placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page"
                                 :options="collect($perPageOptions)->mapWithKeys(fn($n) => [$n => $n.' / halaman'])->all()"
                                 :value="(int)request('per_page', $perPage)" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.assessment-periods.index') }}"
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
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="900px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-800">{{ $it->name }}</td>
                    <td class="px-6 py-4 text-slate-600">
                        {{ optional($it->start_date)->format('d M Y') }} - {{ optional($it->end_date)->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4">
                        @switch($it->status)
                            @case('active')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                                @break
                            @case('locked')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Dikunci</span>
                                @break
                            @case('closed')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700 border border-slate-300">Ditutup</span>
                                @break
                            @default
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">Draft</span>
                        @endswitch
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            @php($today = \Carbon\Carbon::today())
                            {{-- Sembunyikan edit & delete jika periode sudah dikunci atau ditutup --}}
                            @if(!in_array($it->status, ['locked','closed']))
                                <x-ui.icon-button as="a" href="{{ route('admin_rs.assessment-periods.edit', $it) }}" icon="fa-pen-to-square" />
                                <form method="POST" action="{{ route('admin_rs.assessment-periods.destroy', $it) }}" onsubmit="return confirm('Hapus periode ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" variant="danger" />
                                </form>
                            @endif
                            {{-- Tombol kunci hanya saat status aktif --}}
                            @if($it->status === 'active')
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.lock', $it) }}" onsubmit="return confirm('Kunci periode ini?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Kunci</x-ui.button>
                                </form>
                            @endif
                            {{-- Tombol aktifkan kembali muncul saat status locked (human error recovery) --}}
                            @if($it->status === 'locked')
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.activate', $it) }}" onsubmit="return confirm('Aktifkan kembali periode ini?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Aktifkan</x-ui.button>
                                </form>
                            @endif
                            {{-- Tombol tutup hanya saat status locked dan sudah melewati tanggal akhir --}}
                            @if($it->status === 'locked' && $it->end_date && $today->gte($it->end_date))
                                <form method="POST" action="{{ route('admin_rs.assessment_periods.close', $it) }}" onsubmit="return confirm('Tutup periode ini?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Tutup</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $items->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $items->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $items->total() }}</span>
                data
            </div>
            <div>{{ $items->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
