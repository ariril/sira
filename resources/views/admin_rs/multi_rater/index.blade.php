<x-app-layout title="Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian 360°</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 space-y-4">

            {{-- Filter periode --}}
            <form method="GET" class="grid md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select
                        name="period_id"
                        :options="$periods->pluck('name','id')->toArray()"
                        :value="optional($period)->id"
                    />
                </div>
                <div>
                    <x-ui.button type="submit" variant="success">Lihat</x-ui.button>
                </div>
            </form>

            {{-- Konten jika periode terpilih --}}
            @if ($period)
                <div class="pt-2 text-sm text-slate-600">
                    Periode: <b>{{ $period->name }}</b>
                    ({{ $period->start_date->format('d M Y') }} – {{ $period->end_date->format('d M Y') }})
                </div>

                {{-- Banner status jadwal aktif --}}
                @if($window)
                    <div class="mt-3 rounded-xl ring-1 ring-emerald-100 bg-gradient-to-r from-emerald-50 to-white p-4 flex items-start gap-3">
                        <i class="fa-solid fa-bullhorn text-emerald-600 mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-medium text-emerald-800">Penilaian 360° AKTIF</div>
                            <div class="text-sm text-emerald-700">
                                Jadwal: <b>{{ optional($window->start_date)->format('d M Y') }}</b>
                                – <b>{{ optional($window->end_date)->format('d M Y') }}</b>
                                • Kriteria aktif 360: <b>{{ $summary['active360Criteria'] ?? 0 }}</b>
                                • Unit: <b>{{ $summary['unitCount'] ?? 0 }}</b>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $today   = now()->toDateString();
                    $minDate = max($period->start_date->toDateString(), $today);
                    $maxDate = $period->end_date->toDateString();
                @endphp

                {{-- Form buka/ubah jadwal --}}
                <form
                    method="POST"
                    action="{{ route('admin_rs.multi_rater.open') }}"
                    class="grid md:grid-cols-5 gap-4 items-end mt-3"
                >
                    @csrf
                    <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mulai</label>
                        <x-ui.input
                            type="date"
                            name="start_date"
                            value="{{ optional($window)->start_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Selesai</label>
                        <x-ui.input
                            type="date"
                            name="end_date"
                            value="{{ optional($window)->end_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                        />
                    </div>

                    <div class="md:col-span-2 flex gap-3 mt-2 md:mt-0">
                        <x-ui.button type="submit" variant="success">Buka / Ubah Jadwal</x-ui.button>
                    </div>
                </form>
                
                {{-- Ringkasan & Cakupan Penilaian 360 --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
                    <div class="mb-3 font-medium">Status & Cakupan Penilaian 360 – {{ $period->name }}</div>

                    @if (empty($stats))
                        <p class="text-slate-600">
                            Belum ada data penilaian 360 untuk periode ini.
                            @if($window)
                                <br>Jadwal sudah aktif — gunakan tombol "Generate" di bawah untuk menyiapkan cakupan penilai berdasarkan unit dan kriteria aktif.
                            @else
                                <br>Aktifkan jadwal terlebih dahulu untuk memulai penilaian 360.
                            @endif
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full border">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="p-2 text-left">Status</th>
                                        <th class="p-2 text-left">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stats as $status => $total)
                                        <tr>
                                            <td class="p-2 border-t">{{ $status }}</td>
                                            <td class="p-2 border-t">{{ $total }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Actions: Generate & Tutup jadwal --}}
                <div class="mt-4 flex gap-3">
                    <form method="POST" action="{{ route('admin_rs.multi_rater.generate') }}">
                        @csrf
                        <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                        <x-ui.button type="submit" variant="primary" class="h-10 px-4">
                            Generate Cakupan 360
                        </x-ui.button>
                    </form>

                    @if($window)
                        <form method="POST" action="{{ route('admin_rs.multi_rater.close') }}" onsubmit="return confirm('Tutup jadwal 360 untuk periode ini?')">
                            @csrf
                            <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                            <x-ui.button type="submit" variant="outline" class="h-10 px-4">
                                Tutup Jadwal
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
