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
                    <x-ui.select name="status" :value="$status" :options="['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled']" placeholder="(Semua)" />
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
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Poin/Bonus</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">
                        Status
                        <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status menjelaskan ketersediaan tugas untuk diklaim. Jika kuota penuh atau ada klaim yang sedang diproses, tugas akan ditandai tidak tersedia meskipun belum lewat jatuh tempo.">!</span>
                    </th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
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

                    $canOpen = in_array($st, ['draft', 'cancelled', 'closed']) && !$duePast;
                    $canClose = $st === 'open';
                    $canCancel = in_array($st, ['open', 'draft']);
                    $activeClaims = (int)($it->active_claims ?? 0);
                    $reviewWaiting = (int)($it->review_waiting ?? 0);
                    $maxClaims = $it->max_claims ?? null;

                    $statusLabel = 'Draft';
                    $statusClass = 'bg-amber-100 text-amber-700';
                    $statusHint  = 'Tugas belum dibuka untuk diklaim pegawai.';
                    if ($st === 'open') {
                        $statusLabel = 'Tersedia';
                        $statusClass = 'bg-emerald-100 text-emerald-700';
                        $statusHint  = 'Tugas dapat diklaim oleh pegawai medis selama kuota masih ada dan belum lewat jatuh tempo.';
                    } elseif ($st === 'cancelled') {
                        $statusLabel = 'Dibatalkan';
                        $statusClass = 'bg-rose-100 text-rose-700';
                        $statusHint  = 'Tugas dibatalkan oleh kepala unit dan tidak dapat diklaim.';
                    } elseif ($st === 'closed') {
                        if ($duePast) {
                            $statusLabel = 'Kadaluarsa';
                            $statusClass = 'bg-slate-200 text-slate-700';
                            $statusHint  = 'Jatuh tempo sudah lewat, tugas tidak dapat diklaim.';
                        } elseif ($reviewWaiting > 0) {
                            $statusLabel = 'Menunggu Review';
                            $statusClass = 'bg-sky-100 text-sky-700';
                            $statusHint  = 'Ada klaim yang sudah dikirim pegawai dan menunggu tindakan (validasi/persetujuan).';
                        } elseif ($activeClaims > 0) {
                            $isQuotaFull = !empty($maxClaims) && $activeClaims >= (int) $maxClaims;
                            $statusLabel = $isQuotaFull ? 'Kuota Penuh' : 'Sedang Diklaim';
                            $statusClass = $isQuotaFull ? 'bg-slate-200 text-slate-700' : 'bg-cyan-100 text-cyan-800';
                            $statusHint  = $isQuotaFull
                                ? 'Kuota klaim sudah penuh sehingga tugas tidak dapat diklaim lagi.'
                                : 'Ada klaim berjalan yang sedang memakai sebagian kuota.';
                        } else {
                            $statusLabel = 'Ditutup';
                            $statusClass = 'bg-slate-200 text-slate-700';
                            $statusHint  = 'Tugas ditutup secara manual atau kuota sedang penuh.';
                        }
                    }
                    $startLabel = $it->start_date
                        ? \Illuminate\Support\Carbon::parse($it->start_date, 'Asia/Jakarta')->setTimeFromTimeString($it->start_time ?? '00:00')->format('d M Y H:i')
                        : '-';
                    $dueLabel = $it->due_date
                        ? \Illuminate\Support\Carbon::parse($it->due_date, 'Asia/Jakarta')->setTimeFromTimeString($it->due_time ?? '23:59')->format('d M Y H:i')
                        : '-';

                    try {
                        $startLabel = $it->start_date
                            ? \Illuminate\Support\Carbon::parse($it->start_date, $tz)->setTimeFromTimeString($it->start_time ?? '00:00')->format('d M Y H:i')
                            : '-';
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
                    <td class="px-6 py-4">{{ $startLabel }} s/d<br>{{ $dueLabel }}</td>
                    @php
                        $pointsDisplay = is_null($it->points) ? '0' : number_format((float) $it->points, 0, ',', '.');
                        $bonusDisplay = is_null($it->bonus_amount)
                            ? '0'
                            : 'Rp ' . number_format((float) $it->bonus_amount, 0, ',', '.');
                    @endphp
                    <td class="px-6 py-4 text-right">{{ $pointsDisplay }} / {{ $bonusDisplay }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs {{ $statusClass }} cursor-help" title="{{ $statusHint }}{{ $maxClaims ? ' (Kuota: '.$activeClaims.' / '.$maxClaims.')' : '' }}{{ $reviewWaiting ? ' (Menunggu review: '.$reviewWaiting.')' : '' }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2 flex-wrap justify-end">
                            <x-ui.icon-button as="a"
                                href="{{ route('kepala_unit.additional-tasks.edit', $it->id) }}"
                                icon="fa-pen-to-square" />

                            @if ($canOpen)
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.open', $it->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button variant="orange" class="h-9 px-3 text-xs" type="submit">Buka</x-ui.button>
                                </form>
                            @endif

                            @if ($canClose)
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.close', $it->id) }}" onsubmit="return confirm('Tutup tugas ini?')">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button variant="outline" class="h-9 px-3 text-xs" type="submit">Tutup</x-ui.button>
                                </form>
                            @endif

                            @if ($canCancel && $st !== 'cancelled')
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.cancel', $it->id) }}" onsubmit="return confirm('Batalkan tugas ini?')">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button variant="outline" class="h-9 px-3 text-xs" type="submit">Batalkan</x-ui.button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('kepala_unit.additional-tasks.destroy', $it->id) }}"
                                onsubmit="return confirm('Hapus tugas ini?')">
                                @csrf @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" />
                            </form>
                        </div>
                        @if ($st === 'closed' && $duePast && $activeClaims === 0 && $reviewWaiting === 0)
                            <p class="mt-2 text-[11px] text-amber-600">Jatuh tempo sudah lewat. Edit tanggal untuk
                                membuka kembali.</p>
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
