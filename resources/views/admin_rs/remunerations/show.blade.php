<x-app-layout title="Detail Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Remunerasi</h1>
            <div class="flex items-center gap-2">
                @if(empty($item->published_at))
                <form method="POST" action="{{ route('admin_rs.remunerations.publish', $item) }}">
                    @csrf
                    <x-ui.button type="submit" variant="success" class="h-10 px-4 text-sm">Publish</x-ui.button>
                </form>
                @endif
                <x-ui.button as="a" href="{{ route('admin_rs.remunerations.index') }}" variant="success" class="h-10 px-4 text-sm">Kembali</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <dl class="grid md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                <div>
                    <dt class="text-slate-500">Nama Pegawai</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Periode</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->assessmentPeriod->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Jumlah</dt>
                    <dd class="text-slate-800 font-medium">{{ number_format((float)($item->amount ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Status</dt>
                    <dd>
                        @if(!empty($item->published_at))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                        @endif
                    </dd>
                </div>
                @if(!empty($wsm['rows']))
                @php
                    $fmtNum = function($val, int $dec = 2) {
                        if (!is_numeric($val)) return '-';
                        $num = (float)$val;
                        if (abs($num - round($num)) < 0.000001) {
                            return (string)(int) round($num);
                        }
                        $formatted = number_format($num, $dec, '.', '');
                        return rtrim(rtrim($formatted, '0'), '.');
                    };
                    $isEqual = fn($a, $b) => is_numeric($a) && is_numeric($b) && abs((float)$a - (float)$b) < 0.00001;
                @endphp
                <div class="md:col-span-2">
                    <dt class="text-slate-500 mb-1">Ringkasan Perhitungan (WSM)</dt>
                    <dd>
                        <div class="overflow-auto rounded-xl border border-slate-200">
                            <table class="min-w-[760px] w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Kriteria</th>
                                        <th class="px-4 py-3 text-left">Tipe</th>
                                        <th class="px-4 py-3 text-right">Bobot (%)</th>
                                        <th class="px-4 py-3 text-right">Nilai</th>
                                        <th class="px-4 py-3 text-right">Min</th>
                                        <th class="px-4 py-3 text-right">Max</th>
                                        <th class="px-4 py-3 text-right">Ternormalisasi</th>
                                        <th class="px-4 py-3 text-right">Kontribusi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($wsm['rows'] as $row)
                                        @php
                                            $score = (float)($row['score'] ?? 0);
                                            $minVal = (float)($row['min'] ?? 0);
                                            $maxVal = (float)($row['max'] ?? 0);
                                            $minMatch = $isEqual($score, $minVal);
                                            $maxMatch = $isEqual($score, $maxVal) && $maxVal !== 0.0;
                                        @endphp
                                        <tr class="border-t border-slate-100">
                                            <td class="px-4 py-2">{{ $row['criteria_name'] }}</td>
                                            <td class="px-4 py-2">{{ $row['type'] }}</td>
                                            <td class="px-4 py-2 text-right">{{ $fmtNum($row['weight'], 2) }}</td>
                                            <td class="px-4 py-2 text-right">
                                                @if($maxMatch)
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">{{ $fmtNum($row['score'], 2) }}</span>
                                                @elseif($minMatch)
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">{{ $fmtNum($row['score'], 2) }}</span>
                                                @else
                                                    {{ $fmtNum($row['score'], 2) }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-right">{{ $fmtNum($row['min'], 2) }}</td>
                                            <td class="px-4 py-2 text-right">{{ $fmtNum($row['max'], 2) }}</td>
                                            <td class="px-4 py-2 text-right">{{ $fmtNum($row['normalized'], 4) }}</td>
                                            <td class="px-4 py-2 text-right">{{ $fmtNum($row['contribution'], 4) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50 border-t border-slate-200">
                                        <td colspan="7" class="px-4 py-2 text-right font-medium">Total Skor WSM</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ $fmtNum($wsm['total'], 4) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </dd>
                </div>
                @endif

                <div class="md:col-span-2">
                    <dt class="text-slate-500 mb-1">Catatan Perhitungan</dt>
                    <dd>
                        <x-remunerations.calculation-details :details="$calcDetails" />
                    </dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('admin_rs.remunerations.update', $item) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Pembayaran</label>
                        <x-ui.input type="date" name="payment_date" :value="$item->payment_date?->format('Y-m-d')" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status Pembayaran</label>
                        {{-- Sinkronisasi dengan enum RemunerationPaymentStatus (UNPAID/PAID/WITHHELD) yang bernilai: Belum Dibayar, Dibayar, Ditahan --}}
                        <x-ui.select name="payment_status" :options="[
                            'Belum Dibayar' => 'Belum Dibayar',
                            'Dibayar' => 'Dibayar',
                            'Ditahan' => 'Ditahan',
                        ]" :value="$item->payment_status?->value" placeholder="(Pilih)" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" :variant="'success'"  class="h-10 px-5">Simpan</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const dateInput = document.querySelector('input[name="payment_date"]');
            const statusSelect = document.querySelector('select[name="payment_status"]');
            dateInput?.addEventListener('change', () => {
                if (dateInput.value && statusSelect) {
                    statusSelect.value = 'Dibayar';
                }
            });
        })();
    </script>
</x-app-layout>
