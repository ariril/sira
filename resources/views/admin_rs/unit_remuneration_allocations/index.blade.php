<x-app-layout title="Alokasi Remunerasi per Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Alokasi per Unit</h1>
            <div class="flex items-center gap-3">
                <button type="button" id="calc-open" class="inline-flex items-center gap-2 h-12 px-5 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-calculator"></i> Kalkulator
                </button>
                <x-ui.button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.create') }}" variant="success" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-plus mr-2"></i> Tambah Alokasi
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama unit / periode" addonLeft="fa-magnifying-glass" value="{{ $filters['q'] ?? '' }}" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$filters['period_id'] ?? null" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="published" :options="['yes'=>'Published','no'=>'Draft']" :value="$filters['published'] ?? null" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.unit-remuneration-allocations.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="920px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->period->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->unit->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-right">{{ number_format((float)($it->amount ?? 0), 2) }}</td>
                    <td class="px-6 py-4">
                        @if(!empty($it->published_at))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            <x-ui.icon-button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.edit', $it) }}" icon="fa-pen-to-square" />
                            <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.destroy', $it) }}" onsubmit="return confirm('Hapus alokasi ini?')">
                                @csrf
                                @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" variant="danger" />
                            </form>
                            <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.update', $it) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="publish_toggle" value="{{ empty($it->published_at) ? 1 : 0 }}" />
                                @if(empty($it->published_at))
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Publish</x-ui.button>
                                @else
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Jadikan Draft</x-ui.button>
                                @endif
                            </form>
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
            <div>{{ $items->links() }}</div>
        </div>
    </div>

    {{-- Kalkulator sederhana --}}
    <div id="calc-modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden items-center justify-center z-40">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="text-lg font-semibold text-slate-800">Kalkulator Alokasi</div>
                <button type="button" id="calc-close" class="text-slate-500 hover:text-slate-700">✕</button>
            </div>
            <div class="space-y-3 text-sm">
                <label class="block">
                    <span class="text-slate-700">Jumlah Alokasi (Rp)</span>
                    <input type="number" step="0.01" min="0" id="calc-amount" class="mt-1 w-full rounded border-slate-200" />
                </label>
                <label class="block">
                    <span class="text-slate-700">Jumlah Profesi</span>
                    <input type="number" min="1" id="calc-prof-count" class="mt-1 w-full rounded border-slate-200" />
                </label>
                <div class="text-sm text-slate-600">Hasil per profesi: <span id="calc-result" class="font-semibold text-slate-800">0</span></div>
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin_rs.unit-remuneration-allocations.create') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50">Buka Form</a>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const modal = document.getElementById('calc-modal');
        const openBtn = document.getElementById('calc-open');
        const closeBtn = document.getElementById('calc-close');
        const calcAmt = document.getElementById('calc-amount');
        const calcProf = document.getElementById('calc-prof-count');
        const calcRes = document.getElementById('calc-result');
        const toggle = (show) => { if (!modal) return; modal.classList[show ? 'remove' : 'add']('hidden'); modal.classList.add('flex'); };
        openBtn?.addEventListener('click', () => { toggle(true); updateCalc(); });
        closeBtn?.addEventListener('click', () => toggle(false));
        modal?.addEventListener('click', (e) => { if (e.target === modal) toggle(false); });
        const updateCalc = () => {
            const a = parseFloat(calcAmt?.value || '0');
            const c = parseInt(calcProf?.value || '0', 10) || 1;
            const res = c > 0 ? a / c : 0;
            if (calcRes) calcRes.textContent = res.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2});
        };
        calcAmt?.addEventListener('input', updateCalc);
        calcProf?.addEventListener('input', updateCalc);
    })();
    </script>
</x-app-layout>
