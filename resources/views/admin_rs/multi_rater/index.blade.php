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

                @if(!$window)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm space-y-1">
                        <div class="font-semibold">Penilaian 360 untuk periode ini sudah ditutup.</div>
                        <div>Jumlah penilaian 360 yang sudah terisi: <b>{{ $summary['submittedCount'] ?? 0 }}</b></div>
                    </div>
                @endif

                {{-- Banner status jadwal aktif --}}
                @if($window)
                    <div class="mt-3 rounded-xl ring-1 ring-emerald-100 bg-gradient-to-r from-emerald-50 to-white p-4 flex items-start gap-3">
                        <i class="fa-solid fa-bullhorn text-emerald-600 mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-medium text-emerald-800">Penilaian 360° AKTIF</div>
                            <div class="text-sm text-emerald-700 space-y-1">
                                <div>
                                    Jadwal: <b>{{ optional($window->start_date)->format('d M Y') }}</b>
                                    – <b>{{ optional($window->end_date)->format('d M Y') }}</b>
                                </div>
                                <div>Kriteria aktif 360: <b>{{ $summary['active360Criteria'] ?? 0 }}</b></div>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $today   = now()->toDateString();
                    $minDate = max($period->start_date->toDateString(), $today);
                    $maxDate = $period->end_date->toDateString();
                    $windowClosed = $summary['windowClosed'] ?? false;
                @endphp

                {{-- Form buka/ubah jadwal --}}
                <form
                    id="open-360-window"
                    method="POST"
                    action="{{ route('admin_rs.multi_rater.open') }}"
                    class="grid md:grid-cols-4 gap-4 items-end mt-3"
                >
                    @csrf
                    <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Mulai</label>
                        <x-ui.input
                            type="date"
                            name="start_date"
                            value="{{ optional($window)->start_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                            :disabled="$windowClosed"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Selesai</label>
                        <x-ui.input
                            type="date"
                            name="end_date"
                            value="{{ optional($window)->end_date?->toDateString() }}"
                            min="{{ $minDate }}"
                            max="{{ $maxDate }}"
                            required
                            :disabled="$windowClosed"
                        />
                    </div>
                </form>

                <div class="flex flex-wrap gap-3 items-center mt-3">
                    <x-ui.button
                        type="submit"
                        form="open-360-window"
                        variant="success"
                        :disabled="$windowClosed"
                    >
                        Buka / Ubah Jadwal
                    </x-ui.button>

                    @if($window)
                        <form
                            method="POST"
                            action="{{ route('admin_rs.multi_rater.close') }}"
                            onsubmit="return confirm('Tutup jadwal 360 untuk periode ini?')"
                        >
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
