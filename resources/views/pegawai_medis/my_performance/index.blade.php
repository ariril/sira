<x-app-layout title="Kinerja Saya">
    <div class="container-px py-6 space-y-6">
        <h1 class="text-2xl font-semibold text-slate-800">Kinerja Saya</h1>

        @if(!$period)
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700">
                Belum ada periode berjalan saat ini.
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-6">
                <div class="text-sm text-slate-600">Periode berjalan</div>
                <div class="text-lg font-semibold text-slate-800">{{ $period->name }}</div>
                <div class="mt-1 text-sm text-slate-600">
                    {{ optional($period->start_date)->format('d M Y') }} - {{ optional($period->end_date)->format('d M Y') }}
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <x-ui.table min-width="720px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Progres</th>
                        </tr>
                    </x-slot>

                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-slate-800">Kehadiran (Absensi)</td>
                        <td class="px-6 py-4">
                            @if($metrics['attendance_days'] === null)
                                <span class="text-slate-500">Belum tersedia</span>
                            @else
                                <span class="font-medium text-slate-800">{{ (int) $metrics['attendance_days'] }}</span>
                                <span class="text-slate-500">hari hadir</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-slate-800">Jumlah Pasien Ditangani</td>
                        <td class="px-6 py-4">
                            @if($metrics['patient_count'] === null)
                                <span class="text-slate-500">Belum tersedia</span>
                            @else
                                <span class="font-medium text-slate-800">{{ number_format((float) $metrics['patient_count'], 0) }}</span>
                            @endif
                        </td>
                    </tr>
                </x-ui.table>
            </div>
        @endif
    </div>
</x-app-layout>
