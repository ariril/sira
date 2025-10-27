<x-app-layout title="Kontribusi Tambahan">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <h1 class="text-2xl font-semibold">Kontribusi Tambahan</h1>

        {{-- SECTION: Tugas Tersedia (termasuk yang sudah Anda klaim) --}}
        <div class="bg-white rounded-xl border p-4">
            <h2 class="font-semibold mb-3">Tugas Tersedia</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($availableTasks as $t)
                    <div class="border rounded-xl p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $t->title }}</div>
                                <div class="text-xs text-slate-500">Periode: {{ $t->period_name ?? '-' }}</div>
                            </div>
                            <div class="text-right text-sm">
                                <div class="font-semibold">Rp {{ $t->bonus_amount ? number_format($t->bonus_amount,0,',','.') : '-' }}</div>
                                <div class="text-xs text-slate-500">Poin: {{ $t->points ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="text-sm text-slate-600 mt-2 line-clamp-3">{{ $t->description }}</div>
                        <div class="mt-2 text-xs text-slate-500">Klaim: {{ $t->claims_used }} / {{ $t->max_claims ?? '∞' }}</div>
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-xs text-slate-500">Jatuh tempo: {{ \Illuminate\Support\Carbon::parse($t->due_date)->format('d M Y') }}</div>
                            <div class="flex items-center gap-2">
                                @if($t->my_claim_id)
                                    <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.cancel', $t->my_claim_id) }}" onsubmit="return confirm('Batalkan klaim tugas ini?')">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-md border text-slate-700 hover:bg-slate-50">Batalkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.complete', $t->my_claim_id) }}">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-md bg-cyan-600 text-white hover:bg-cyan-700">Selesai</button>
                                    </form>
                                @elseif($t->available)
                                    <form method="POST" action="{{ route('pegawai_medis.additional_tasks.claim', $t->id) }}">
                                        @csrf
                                        <button class="px-3 py-1.5 rounded-md bg-cyan-600 text-white hover:bg-cyan-700">Klaim</button>
                                    </form>
                                @else
                                    <span class="text-xs text-red-600">Kuota habis</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Belum ada tugas yang tersedia.</div>
                @endforelse
            </div>
        </div>

        {{-- SECTION: Klaim Aktif Saya --}}
        <div class="bg-white rounded-xl border p-4">
            <h2 class="font-semibold mb-3">Klaim Aktif Saya</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left px-4 py-3">Tugas</th>
                            <th class="text-left px-4 py-3">Periode</th>
                            <th class="text-left px-4 py-3">Klaim Pada</th>
                            <th class="text-left px-4 py-3">Batas Batal</th>
                            <th class="text-left px-4 py-3">Bonus</th>
                            <th class="text-left px-4 py-3">Poin</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myActiveClaims as $c)
                            <tr class="border-t">
                                <td class="px-4 py-3">{{ $c->title }}</td>
                                <td class="px-4 py-3">{{ $c->period_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ optional($c->claimed_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">{{ optional($c->cancel_deadline_at)->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $c->bonus_amount ? 'Rp '.number_format($c->bonus_amount,0,',','.') : '-' }}</td>
                                <td class="px-4 py-3">{{ $c->points ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.cancel', $c->claim_id) }}" onsubmit="return confirm('Batalkan klaim ini?')">
                                            @csrf
                                            <button class="px-3 py-1.5 rounded-md border text-slate-700 hover:bg-slate-50">Batalkan</button>
                                        </form>
                                        <form method="POST" action="{{ route('pegawai_medis.additional_task_claims.complete', $c->claim_id) }}">
                                            @csrf
                                            <button class="px-3 py-1.5 rounded-md bg-cyan-600 text-white hover:bg-cyan-700">Selesai</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">Tidak ada klaim aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SECTION: Tugas Selesai --}}
        <div class="bg-white rounded-xl border p-4">
            <h2 class="font-semibold mb-3">Tugas Selesai</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left px-4 py-3">Tugas</th>
                            <th class="text-left px-4 py-3">Periode</th>
                            <th class="text-left px-4 py-3">Selesai Pada</th>
                            <th class="text-left px-4 py-3">Bonus Diterima</th>
                            <th class="text-left px-4 py-3">Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myCompletedClaims as $c)
                            <tr class="border-t">
                                <td class="px-4 py-3">{{ $c->title }}</td>
                                <td class="px-4 py-3">{{ $c->period_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ optional($c->completed_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $c->bonus_amount ? 'Rp '.number_format($c->bonus_amount,0,',','.') : '-' }}</td>
                                <td class="px-4 py-3">{{ $c->points ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada tugas selesai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
