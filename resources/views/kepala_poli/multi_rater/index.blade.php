<x-app-layout title="Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Penilaian 360° Ditugaskan</h1>
    </x-slot>

    <div class="container-px py-6">
        @if($assessments->isEmpty())
            <p class="text-slate-600">Tidak ada tugas penilaian saat ini.</p>
        @else
            <div class="overflow-x-auto bg-white rounded-2xl shadow-sm border border-slate-100">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="p-3 text-left">Assessee</th>
                            <th class="p-3 text-left">Periode</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assessments as $a)
                            <tr class="border-t">
                                <td class="p-3">#{{ $a->assessee_id }}</td>
                                <td class="p-3">#{{ $a->assessment_period_id }}</td>
                                <td class="p-3">{{ $a->status }}</td>
                                <td class="p-3"><a class="text-purple-600" href="{{ route('kepala_poliklinik.multi_rater.show', $a) }}">Isi</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
