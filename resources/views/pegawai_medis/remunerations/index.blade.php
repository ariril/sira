<x-app-layout title="Remunerasi Saya">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Remunerasi Saya</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        @php
            $banners = $banners ?? [];
            $statusCard = $statusCard ?? null;
        @endphp

        @if(!empty($banners))
            <div class="space-y-3">
                @foreach($banners as $b)
                    @php
                        $type = $b['type'] ?? 'info';
                        $msg = $b['message'] ?? '';
                    @endphp
                    @if($msg)
                        @if($type === 'warning')
                            <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm flex items-start gap-2">
                                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                                <span>{{ $msg }}</span>
                            </div>
                        @else
                            <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm flex items-start gap-2">
                                <i class="fa-solid fa-circle-info mt-0.5"></i>
                                <span>{{ $msg }}</span>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <x-ui.table min-width="980px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Jumlah</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status Pembayaran</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Dipublikasikan</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($items as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $item->assessmentPeriod->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
                        <td class="px-6 py-4">{{ $item->payment_status?->value ?? '-' }}</td>
                        <td class="px-6 py-4">{{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <a href="{{ route('pegawai_medis.remunerations.show', $item->id) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Detail</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada data remunerasi.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div class="pt-2 flex justify-end">{{ $items->withQueryString()->links() }}</div>

        {{-- Status Proses Penilaian & Remunerasi (informasi, tidak mencampur dengan tabel remunerasi) --}}
        @if(!empty($statusCard))
            <x-section title="Status Proses Penilaian & Remunerasi" class="mt-4">
                @if(($statusCard['mode'] ?? null) === 'active')
                    <div class="flex items-start justify-between rounded-xl ring-1 ring-slate-100 p-4">
                        <div>
                            <div class="font-medium">Periode {{ $statusCard['period_name'] ?? '-' }}</div>
                            <div class="mt-1 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700 border border-sky-100">Periode Berjalan</span>
                            </div>
                            <div class="mt-2 text-sm text-slate-600">{{ $statusCard['message'] ?? '' }}</div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between rounded-xl ring-1 ring-slate-100 p-3">
                        <div>
                            <div class="font-medium">Periode {{ $statusCard['period_name'] ?? '-' }}</div>
                            <div class="text-sm text-slate-600">
                                Level disetujui: {{ $statusCard['highestApproved'] ?? 0 }} • Status saat ini:
                                @php $st = $statusCard['currentStatus'] ?? 'pending'; @endphp
                                @if($st === 'approved')
                                    <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700">Approved</span>
                                @elseif($st === 'rejected')
                                    <span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                                @endif
                            </div>

                            @php
                                $levels = collect($statusCard['levels'] ?? []);
                                $byLevel = $levels->keyBy(fn($lv) => (int)($lv->level ?? 0));
                            @endphp
                            <div class="mt-3 overflow-auto rounded-xl border border-slate-200">
                                <table class="min-w-[520px] w-full text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Level</th>
                                            <th class="px-4 py-2 text-left">Status</th>
                                            <th class="px-4 py-2 text-left">Waktu Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($i = 1; $i <= 3; $i++)
                                            @php
                                                $lv = $byLevel->get($i);
                                                $st = $lv->status ?? null;
                                            @endphp
                                            <tr class="border-t border-slate-100">
                                                <td class="px-4 py-2 font-medium">Level {{ $i }}</td>
                                                <td class="px-4 py-2">
                                                    @if($st === 'approved')
                                                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700">Approved</span>
                                                    @elseif($st === 'rejected')
                                                        <span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                                                    @elseif($st === 'pending')
                                                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                                                    @else
                                                        <span class="text-slate-500">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-slate-600">
                                                    @php $acted = $lv->acted_at ?? null; @endphp
                                                    {{ $acted ? \Carbon\Carbon::parse($acted)->format('d M Y H:i') : '-' }}
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if(!empty($statusCard['assessment_id']))
                            <a href="{{ route('pegawai_medis.assessments.show', $statusCard['assessment_id']) }}" class="px-3 py-1.5 rounded-md ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50">Detail Penilaian</a>
                        @endif
                    </div>
                @endif
            </x-section>
        @endif
    </div>
</x-app-layout>
