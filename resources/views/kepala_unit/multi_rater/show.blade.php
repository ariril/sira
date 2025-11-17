<x-app-layout title="Isi Penilaian 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Isi Penilaian 360°</h1>
    </x-slot>
    <div class="container-px py-6">
        <form method="POST" action="{{ route('kepala_unit.multi_rater.submit', $assessment) }}" class="space-y-4">
            @csrf
            <div class="overflow-x-auto bg-white rounded-2xl shadow-sm border border-slate-100">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-slate-50"><th class="p-3 text-left">Kriteria</th><th class="p-3 text-left">Skor (0-100)</th><th class="p-3 text-left">Komentar</th></tr>
                    </thead>
                    <tbody>
                    @foreach($criterias as $c)
                        @php($d = $details[$c->id] ?? null)
                        <tr class="border-t">
                            <td class="p-3">{{ $c->name ?? ('Kriteria #'.$c->id) }}</td>
                            <td class="p-3"><input type="number" name="scores[{{ $c->id }}]" value="{{ old('scores.'.$c->id, optional($d)->score) }}" min="0" max="100" class="w-28 border rounded p-1" /></td>
                            <td class="p-3"><input type="text" name="comments[{{ $c->id }}]" value="{{ old('comments.'.$c->id, optional($d)->comment) }}" class="w-full border rounded p-1" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <button class="px-4 py-2 bg-amber-600 text-white rounded" type="submit">Kirim</button>
            </div>
        </form>
    </div>
</x-app-layout>
