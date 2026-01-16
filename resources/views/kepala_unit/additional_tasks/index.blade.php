<x-app-layout title="Tugas Tambahan Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Tugas Tambahan Unit</h1>
            <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.create') }}" variant="orange"
                class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Buat Tugas
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @unless($activePeriod ?? null)
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                <div class="font-semibold">Tidak ada periode yang aktif saat ini.</div>
                <div>Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.</div>
            </div>
        @endunless

        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" :value="$q" placeholder="Judul / Periode"
                        addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :value="$status" :options="['open' => 'Open', 'closed' => 'Closed']" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Filter Periode
                        <span class="inline-block ml-1 text-amber-600 cursor-help"
                            title="Gunakan untuk menampilkan daftar tugas pada periode tertentu.">!</span>
                    </label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name', 'id')" :value="$periodId" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('kepala_unit.additional-tasks.index') }}"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 text-base">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        <x-ui.table min-width="1000px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Judul</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Jatuh Tempo</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Poin</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">
                        Status
                        <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status menjelaskan ketersediaan tugas untuk diklaim. Jika kuota penuh atau ada klaim yang sedang diproses, tugas akan ditandai tidak tersedia meskipun belum lewat jatuh tempo.">!</span>
                    </th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">
                        Aksi
                        <span class="inline-block ml-1 text-amber-600 cursor-help" title="Buka/Tutup untuk mengatur ketersediaan tugas. Edit/Hapus hanya aman saat belum ada klaim.">!</span>
                    </th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                @php
                    $st = $it->status;
                    $tz = config('app.timezone');
                    $dueTime = $it->due_time ?? '23:59';
                    $duePast = $it->due_date
                        ? \Illuminate\Support\Carbon::parse($it->due_date, 'Asia/Jakarta')->setTimeFromTimeString($dueTime)->isPast()
                        : false;
                    try {
                        $duePast = $it->due_date
                            ? \Illuminate\Support\Carbon::parse($it->due_date, $tz)->setTimeFromTimeString($dueTime)->isPast()
                            : false;
                    } catch (\Exception $e) {
                        // fallback tetap seperti sebelumnya
                    }

                    $totalClaims = (int)($it->total_claims ?? 0);
                    $activeClaims = (int)($it->active_claims ?? 0);
                    $reviewWaiting = (int)($it->review_waiting ?? 0);
                    $finishedClaims = (int)($it->finished_claims ?? 0);
                    $maxClaims = $it->max_claims ?? null;

                    $isQuotaDone = !empty($maxClaims) && $activeClaims >= (int) $maxClaims;
                    $canOpen = ($st === 'closed') && !$duePast && !$isQuotaDone && ($reviewWaiting === 0);
                    $canClose = $st === 'open';
                    $canEdit = ($totalClaims === 0);
                    $canDelete = ($totalClaims === 0);

                    $statusLabel = 'Closed';
                    $statusClass = 'bg-slate-200 text-slate-700';
                    $statusHint  = 'Tugas ditutup dan tidak dapat disubmit.';
                    if ($st === 'open') {
                        $statusLabel = 'Open';
                        $statusClass = 'bg-emerald-100 text-emerald-700';
                        $statusHint  = 'Tugas dapat disubmit oleh pegawai medis selama kuota masih ada dan belum lewat jatuh tempo.';
                    } else {
                        if ($reviewWaiting > 0) {
                            $statusLabel = 'Menunggu Review';
                            $statusClass = 'bg-sky-100 text-sky-700';
                            $statusHint  = 'Ada klaim yang menunggu keputusan kepala unit.';
                        } elseif ($isQuotaDone) {
                            $statusLabel = 'Kuota Penuh';
                            $statusClass = 'bg-slate-200 text-slate-700';
                            $statusHint  = 'Kuota klaim sudah terpenuhi.';
                        } elseif ($duePast) {
                            $statusLabel = 'Closed';
                            $statusClass = 'bg-slate-200 text-slate-700';
                            $statusHint  = 'Jatuh tempo sudah lewat.';
                        }
                    }
                    $dueLabel = $it->due_date
                        ? \Illuminate\Support\Carbon::parse($it->due_date, 'Asia/Jakarta')->setTimeFromTimeString($it->due_time ?? '23:59')->format('d M Y H:i')
                        : '-';

                    try {
                        $dueLabel = $it->due_date
                            ? \Illuminate\Support\Carbon::parse($it->due_date, $tz)->setTimeFromTimeString($it->due_time ?? '23:59')->format('d M Y H:i')
                            : '-';
                    } catch (\Exception $e) {
                        // fallback tetap seperti sebelumnya
                    }
                @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->title }}</td>
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    @php
                        $pointsDisplay = is_null($it->points) ? '0' : number_format((float) $it->points, 0, ',', '.');
                    @endphp
                    <td class="px-6 py-4">{{ $dueLabel }}</td>
                    <td class="px-6 py-4 text-right">{{ $pointsDisplay }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs {{ $statusClass }} cursor-help" title="{{ $statusHint }}{{ $maxClaims ? ' (Kuota: '.$activeClaims.' / '.$maxClaims.')' : '' }}{{ $reviewWaiting ? ' (Menunggu review: '.$reviewWaiting.')' : '' }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex items-center gap-2 justify-end">
                            @if ($canEdit)
                                <x-ui.icon-button as="a"
                                    href="{{ route('kepala_unit.additional-tasks.edit', $it->id) }}"
                                    icon="fa-pen-to-square"
                                    class="w-9 h-9" />
                            @endif

                            @if ($canOpen)
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.open', $it->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button variant="outline" class="h-9 px-3 text-xs" type="submit">Buka</x-ui.button>
                                </form>
                            @endif

                            @if ($canClose)
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.close', $it->id) }}" onsubmit="return confirm('Tutup tugas ini?')">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button variant="outline" class="h-9 px-3 text-xs" type="submit">Tutup</x-ui.button>
                                </form>
                            @endif

                            @if ($canDelete)
                                <form method="POST" action="{{ route('kepala_unit.additional-tasks.destroy', $it->id) }}"
                                    onsubmit="return confirm('Hapus tugas ini?')">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" class="w-9 h-9" />
                                </form>
                            @endif
                        </div>
                        @if ($st === 'closed' && $duePast && $activeClaims === 0 && $reviewWaiting === 0 && $finishedClaims === 0)
                            <p class="mt-2 text-[11px] text-amber-600">Jatuh tempo sudah lewat. Edit tanggal untuk membuka kembali.</p>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td>
                </tr>
            @endforelse
        </x-ui.table>

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
