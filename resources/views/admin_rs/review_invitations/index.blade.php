<x-app-layout title="Daftar Link Undangan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Daftar Link Undangan</h1>

            <div class="flex items-center gap-3 flex-wrap justify-end">
                <form method="POST" action="{{ route('admin_rs.review_invitations.test_email') }}" class="flex items-center gap-2">
                    @csrf
                    <x-ui.input
                        type="email"
                        name="email"
                        placeholder="Test email tujuan"
                        value="{{ old('email') }}"
                        class="h-11"
                    />
                    <button type="submit"
                        class="inline-flex items-center gap-2 h-11 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">
                        <i class="fa-solid fa-vial"></i>
                        Test Kirim Email
                    </button>
                </form>

                @if(!empty($selectedPeriodId))
                    <form method="POST" action="{{ route('admin_rs.review_invitations.send_email_bulk') }}">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ (int) $selectedPeriodId }}" />
                        <button type="submit"
                            class="inline-flex items-center gap-2 h-11 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">
                            <i class="fa-solid fa-paper-plane"></i>
                            Kirim Email (Bulk)
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @error('email')
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                {{ $message }}
            </div>
        @enderror

        @php
            $mailTest = session('mail_test_result');
        @endphp
        @if(!empty($mailTest) && is_array($mailTest))
            @php
                $ok = (bool) ($mailTest['success'] ?? false);
                $smtp = (array) ($mailTest['smtp'] ?? []);
                $smtpSummary = trim((string) (($smtp['host'] ?? '-') . ':' . ($smtp['port'] ?? '-') . ' ' . ($smtp['encryption'] ?? '')));
            @endphp
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="space-y-1">
                        <div class="text-sm font-semibold text-slate-800">Hasil Test Kirim Email</div>
                        <div class="text-xs text-slate-500">Bukti yang dicatat: SMTP meta + Message-ID (jika tersedia). Log detail ada di storage/logs/mail.log.</div>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $ok ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' }}">
                        {{ $ok ? 'SUCCESS' : 'FAILED' }}
                    </span>
                </div>

                <div class="mt-4 grid gap-2 text-sm text-slate-700">
                    <div><span class="font-medium">To:</span> {{ $mailTest['to'] ?? ($mailTest['to_email'] ?? '-') }}</div>
                    <div><span class="font-medium">Sent At:</span> {{ $mailTest['sent_at'] ?? '-' }}</div>
                    <div><span class="font-medium">Message-ID:</span> {{ $mailTest['message_id'] ?? '-' }}</div>
                    <div><span class="font-medium">SMTP:</span> {{ $smtpSummary !== '' ? $smtpSummary : '-' }}</div>
                    @if(!$ok)
                        <div class="text-rose-700"><span class="font-medium">Error:</span> {{ $mailTest['error'] ?? 'Unknown error' }}</div>
                    @endif
                </div>
            </div>
        @endif

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
                    <th class="px-6 py-4 text-left whitespace-nowrap">Dikirim</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Dibuka</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Digunakan</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kadaluarsa</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @php
                $rows = $items ? $items->items() : [];
            @endphp
            @forelse($rows as $it)
                @php
                    $status = (string) ($it->status ?? '');
                    $badgeMap = [
                        'used' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        'clicked' => 'bg-amber-50 text-amber-800 border-amber-100',
                        'sent' => 'bg-sky-50 text-sky-700 border-sky-100',
                        'created' => 'bg-slate-50 text-slate-700 border-slate-200',
                        'expired' => 'bg-rose-50 text-rose-700 border-rose-100',
                        'cancelled' => 'bg-slate-50 text-slate-700 border-slate-200',
                    ];
                    $badgeClass = $badgeMap[$status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                    // $link = !empty($it->token_plain) ? url('/reviews/invite/' . $it->token_plain) : null;

                    $email = trim((string) ($it->email ?? ''));
                    $canSendEmail = $email !== '' && $it->used_at === null;
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
                    {{-- <td class="px-6 py-4">
                        @if($link)
                            <a class="text-indigo-600 hover:underline break-all" href="{{ $link }}" target="_blank" rel="noreferrer">{{ $link }}</a>
                        @else
                            -
                        @endif
                    </td> --}}
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->sent_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->clicked_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->used_at)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-slate-700">{{ optional($it->expires_at)->format('d M Y H:i') ?? '-' }}</td>

                    <td class="px-6 py-4">
                        <form method="POST" action="{{ route('admin_rs.review_invitations.send_email', ['id' => $it->id]) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-semibold border {{ $canSendEmail ? 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50' : 'bg-slate-50 border-slate-200 text-slate-400 cursor-not-allowed' }}"
                                {{ $canSendEmail ? '' : 'disabled' }}>
                                <i class="fa-solid fa-paper-plane"></i>
                                Kirim Email
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-6 py-8 text-center text-slate-500">Belum ada undangan pada periode ini.</td>
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
