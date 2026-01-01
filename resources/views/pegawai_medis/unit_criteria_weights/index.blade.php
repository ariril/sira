<x-app-layout title="Kriteria & Bobot Aktif">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Kriteria & Bobot Aktif</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="text-sm text-slate-600">Periode aktif: <span class="font-medium text-slate-800">{{ $periodName ?? '-' }}</span></div>
        </div>

        <x-ui.table min-width="720px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Kriteria</th>
                    <th class="px-6 py-4 text-left">Tipe</th>
                    <th class="px-6 py-4 text-right">Bobot</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->criteria_name }}</td>
                    <td class="px-6 py-4">{{ $it->criteria_type }}</td>
                    <td class="px-6 py-4 text-right">{{ number_format((float)($it->weight ?? 0),2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">Belum ada bobot aktif pada periode ini.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="space-y-3">
            <h4 class="text-sm font-semibold text-slate-700">Bobot Penilai 360 Aktif</h4>
            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Kriteria (360)</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Rincian</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Total</th>
                    </tr>
                </x-slot>
                @forelse(($rater360 ?? collect()) as $row)
                    @php($sum = (float) ($row['sum'] ?? 0))
                    @php($parts = (array) ($row['parts'] ?? []))
                    @php($labels = ['supervisor' => 'Atasan', 'peer' => 'Rekan', 'subordinate' => 'Bawahan', 'self' => 'Diri'])
                    @php($chunks = [])
                    @foreach($labels as $k => $lbl)
                        @php($val = (float) ($parts[$k] ?? 0))
                        @if($val > 0)
                            @php($chunks[] = $lbl . ' ' . number_format($val, 0))
                        @endif
                    @endforeach
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row['criteria_name'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-700">{{ !empty($chunks) ? implode(' / ', $chunks) : '-' }}</td>
                        <td class="px-6 py-4 text-right font-semibold">{{ number_format($sum, 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-slate-500">Belum ada bobot penilai 360 aktif pada periode ini.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
