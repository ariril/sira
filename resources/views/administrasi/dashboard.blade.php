@extends('layouts.app')

@section('content')
    <div class="container-px py-6">
        <h1 class="text-2xl font-semibold mb-4">Dashboard Administrasi</h1>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card metric="{{ $ops['antrian_hari_ini'] ?? 0 }}"   label="Antrian Hari Ini" />
            <x-card metric="{{ $ops['kehadiran_hari_ini'] ?? 0 }}" label="Kehadiran Pegawai" />
            <x-card metric="{{ $ops['ulasan_hari_ini'] ?? 0 }}"    label="Ulasan Masuk" />
            <x-card metric="{{ ($ops['jadwal_dokter_besok'] ?? collect())->count() }}" label="Jadwal Dokter (Besok)" />
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="col-span-1 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Ringkasan Operasional (Hari Ini)</h2>
                <div id="admTodayBar"></div>
            </div>

            <div class="col-span-1 lg:col-span-2 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Jadwal Dokter Besok</h2>
                @php $rows = $ops['jadwal_dokter_besok'] ?? collect(); @endphp
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
                                <tr class="border-b">
                                    <td class="py-2">{{ $r->nama_dokter ?? $r->dokter ?? '-' }}</td>
                                    <td class="py-2">{{ \Illuminate\Support\Carbon::parse($r->tanggal)->format('d M Y') }}</td>
                                    <td class="py-2">{{ ($r->jam_mulai ?? '') . (isset($r->jam_selesai)?' - '.$r->jam_selesai:'') }}</td>
                                    <td class="py-2">{{ $r->ruangan ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            const data = {
                antrian: Number(@json($ops['antrian_hari_ini'] ?? 0)) || 0,
                hadir:   Number(@json($ops['kehadiran_hari_ini'] ?? 0)) || 0,
                ulasan:  Number(@json($ops['ulasan_hari_ini'] ?? 0)) || 0,
            };
            const ctx = document.getElementById('admTodayChart');
            if (ctx) new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Antrian','Kehadiran','Ulasan'],
                    datasets: [{ label: 'Jumlah', data: [data.antrian, data.hadir, data.ulasan] }]
                },
                options: {
                    scales: { y: { beginAtZero: true, ticks: { precision:0 } } },
                    plugins: { legend: { display: false } }
                }
            });
        })();
    </script>

    {{--Ini bisa di hapus--}}
    <script>
        (() => {
            const val = {
                antrian: Number(@json($ops['antrian_hari_ini'] ?? 0)) || 0,
                hadir:   Number(@json($ops['kehadiran_hari_ini'] ?? 0)) || 0,
                ulasan:  Number(@json($ops['ulasan_hari_ini'] ?? 0)) || 0,
            };
            new ApexCharts(document.querySelector('#admTodayBar'), {
                chart: { type: 'bar', height: 220 },
                series: [{ name: 'Jumlah', data: [val.antrian, val.hadir, val.ulasan] }],
                xaxis: { categories: ['Antrian','Kehadiran','Ulasan'] },
                dataLabels: { enabled: true },
                plotOptions: { bar: { borderRadius: 6 } }
            }).render();
        })();
    </script>
@endpush
