<x-app-layout title="Undangan 360">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Undangan Penilaian 360°</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
            <form method="POST" action="{{ route('admin_rs.multi_rater.generate') }}" class="grid md:grid-cols-4 gap-4 items-end">
                @csrf
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">ID Periode</label>
                    <input type="number" name="period_id" value="{{ old('period_id', request('period_id', $periodId)) }}" class="mt-1 border rounded w-full p-2" required min="1" />
                </div>
                <label class="inline-flex items-center gap-2 md:col-span-1">
                    <input type="checkbox" name="reset" value="1" class="rounded" />
                    <span class="text-sm">Reset undangan periode ini</span>
                </label>
                <div>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Generate</button>
                </div>
            </form>
        </div>

        @if($periodId)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="mb-3 font-medium">Ringkasan Periode #{{ $periodId }}</div>
                @if(empty($stats))
                    <p class="text-slate-600">Belum ada undangan.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full border">
                            <thead>
                                <tr class="bg-slate-50"><th class="p-2 text-left">Status</th><th class="p-2 text-left">Jumlah</th></tr>
                            </thead>
                            <tbody>
                                @foreach($stats as $status => $total)
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
        @endif
    </div>
</x-app-layout>
