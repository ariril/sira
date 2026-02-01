<x-app-layout title="Daftar Link Undangan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Daftar Link Undangan</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if(!empty($periodWarning))
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                {{ $periodWarning }}
            </div>
        @endif

        @if(!empty($periodOptions))
            {{-- FILTERS (Periode saja) --}}
            <form method="GET" action="{{ route('admin_rs.review_invitations.index') }}" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
                <div class="grid gap-5 md:grid-cols-12">
                    <div class="md:col-span-6">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                        <x-ui.select name="period_id" :options="$periodOptions" :value="$selectedPeriodId ?? null" />
                    </div>

                    <div class="md:col-span-6 flex items-end justify-end gap-3">
                        <a href="{{ route('admin_rs.review_invitations.index') }}"
                            class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>

                        <button type="submit"
                            class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                            <i class="fa-solid fa-filter"></i> Terapkan
                        </button>
                    </div>
                </div>
            </form>
        @endif

        {{-- TABLE --}}
        <x-ui.table min-width="1400px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Registration Ref</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pasien</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kontak</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Link Undangan</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Dikirim</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Dibuka</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Digunakan</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kadaluarsa</th>
                </tr>
            </x-slot>

            @php $rows = $items ? $items->items() : []; @endphp
            @forelse($rows as $it)
                @php
                    $status = (string) ($it->status ?? '');
                    $badgeMap = [
                        'used' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        'opened' => 'bg-amber-50 text-amber-800 border-amber-100',
                        'sent' => 'bg-sky-50 text-sky-700 border-sky-100',
                        'created' => 'bg-slate-50 text-slate-700 border-slate-200',
                        'expired' => 'bg-rose-50 text-rose-700 border-rose-100',
                        'cancelled' => 'bg-slate-50 text-slate-700 border-slate-200',
                    ];
                    $badgeClass = $badgeMap[$status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                    $link = !empty($it->token_plain) ? url('/reviews/invite/' . $it->token_plain) : null;
                @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-800">{{ $it->registration_ref ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->patient_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->contact ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->unit?->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $badgeClass }}">
                            {{ strtoupper($status !== '' ? $status : '-') }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($link)
                            <a class="text-indigo-600 hover:underline break-all" href="{{ $link }}" target="_blank" rel="noreferrer">{{ $link }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->sent_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->opened_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->used_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->expires_at)->format('d M Y H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-8 text-center text-slate-500">Belum ada undangan pada periode ini.</td>
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
                <span class="font-medium text-slate-800">{{ $items->total() ?? 0 }}</span>
                data
            </div>
            <div>{{ $items?->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
