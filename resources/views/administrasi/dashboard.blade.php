<x-app-layout title="Dashboard Administrasi">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Administrasi</h1>
    </x-slot>

    <div class="container-px py-6">
        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card metric="{{ $ops['antrian_hari_ini']   ?? 0 }}" label="Antrian Hari Ini" />
            <x-card metric="{{ $ops['kehadiran_hari_ini'] ?? 0 }}" label="Kehadiran Pegawai" />
            <x-card metric="{{ $ops['ulasan_hari_ini']    ?? 0 }}" label="Ulasan Masuk" />
            <x-card metric="{{ collect($ops['jadwal_dokter_besok'] ?? [])->count() }}" label="Jadwal Dokter (Besok)" />
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Ringkasan Operasional --}}
            <div class="col-span-1 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Ringkasan Operasional (Hari Ini)</h2>
                <div id="admTodayBar" class="min-h-[220px]"></div>
            </div>

            {{-- Jadwal Dokter Besok --}}
            <div class="col-span-1 lg:col-span-2 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Jadwal Dokter Besok</h2>

                @php
                    $rows = collect($ops['jadwal_dokter_besok'] ?? []);
                @endphp

                @if($rows->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada jadwal.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Dokter</th>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2">Jam</th>
                                <th class="py-2">Ruangan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($rows as $r)
                                @php
                                    $tgl     = data_get($r, 'tanggal');
                                    $mulai   = data_get($r, 'jam_mulai');
                                    $selesai = data_get($r, 'jam_selesai');
                                @endphp
                                <tr class="border-b">
                                    <td class="py-2">{{ data_get($r, 'nama_dokter') ?? data_get($r, 'dokter') ?? '-' }}</td>
                                    <td class="py-2">{{ $tgl ? \Illuminate\Support\Carbon::parse($tgl)->format('d M Y') : '-' }}</td>
                                    <td class="py-2">
                                        {{ $mulai ? ($mulai . ($selesai ? ' - '.$selesai : '')) : '-' }}
                                    </td>
                                    <td class="py-2">{{ data_get($r, 'ruangan') ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        @once
            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        @endonce
        <script>
            (() => {
                const el = document.querySelector('#admTodayBar');
                if (!el || typeof ApexCharts === 'undefined') return;

                const val = {
                    antrian: Number(@json($ops['antrian_hari_ini']   ?? 0)) || 0,
                    hadir:   Number(@json($ops['kehadiran_hari_ini'] ?? 0)) || 0,
                    ulasan:  Number(@json($ops['ulasan_hari_ini']    ?? 0)) || 0,
                };

                new ApexCharts(el, {
                    chart: { type: 'bar', height: 220, toolbar: { show: false } },
                    series: [{ name: 'Jumlah', data: [val.antrian, val.hadir, val.ulasan] }],
                    xaxis: { categories: ['Antrian','Kehadiran','Ulasan'] },
                    dataLabels: { enabled: true },
                    plotOptions: { bar: { borderRadius: 6 } }
                }).render();
            })();
        </script>
    @endpush
</x-app-layout>
