<x-app-layout title="Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian 360°</h1>
    </x-slot>
    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            @if($window && $activePeriod)
                <div class="text-sm text-slate-700">
                    Penilaian 360 dimulai dari <b>{{ $window->start_date->format('d M Y') }}</b>
                    hingga <b>{{ $window->end_date->format('d M Y') }}</b>
                    pada periode <b>{{ $activePeriod->name }}</b>.
                    @if(now()->toDateString() <= $window->end_date->toDateString())
                        <span class="inline-block ml-2 text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded">Anda dapat mengisi penilaian sekarang.</span>
                    @endif
                </div>
            @else
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-100 px-3 py-2 rounded">
                    Penilaian 360 belum dibuka.
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="p-4 font-medium">Tugas Penilaian Anda</div>
            @if($assessments->isEmpty())
                <p class="px-4 pb-4 text-slate-600">Tidak ada tugas penilaian saat ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="p-3 text-left">Nama</th>
                                <th class="p-3 text-left">Unit</th>
                                <th class="p-3 text-left">Periode</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($assessments as $a)
                            <tr class="border-t">
                                <td class="p-3">{{ $a->assessee?->name ?? '—' }}</td>
                                <td class="p-3">{{ $a->assessee?->unit?->name ?? '—' }}</td>
                                <td class="p-3">{{ $a->period?->name ?? '—' }}</td>
                                <td class="p-3">
                                    <span class="text-xs px-2 py-1 rounded border {{ $a->status === 'in_progress' ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-slate-50 text-slate-700 border-slate-100' }}">{{ str_replace('_',' ', $a->status) }}</span>
                                </td>
                                <td class="p-3 text-right">
                                    <a class="inline-flex items-center gap-2 px-3 py-1.5 rounded bg-emerald-600 text-white hover:bg-emerald-700" href="{{ route('pegawai_medis.multi_rater.show', $a) }}">
                                        <i class="fa-solid fa-pen"></i> Isi
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="mb-3 font-medium">Ringkasan Nilai 360 Anda</div>
            <form method="GET" class="flex items-end gap-3 mb-3">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Periode</label>
                    <select name="summary_period_id" class="border rounded-lg px-3 py-2">
                        @foreach($periods as $p)
                            <option value="{{ $p->id }}" @selected(optional($summaryPeriod)->id === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Lihat</button>
            </form>
            @if(!is_null($avgScore))
                <div class="text-slate-700">Nilai rata-rata Anda pada periode <b>{{ $summaryPeriod->name }}</b>: <span class="text-xl font-semibold">{{ number_format($avgScore, 2) }}</span></div>
            @else
                <div class="text-slate-500">Belum ada penilaian terkumpul pada periode ini.</div>
            @endif
        </div>
    </div>
</x-app-layout>
