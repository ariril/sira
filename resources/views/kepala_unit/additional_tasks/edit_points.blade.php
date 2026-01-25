<x-app-layout title="Edit Poin Tugas">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Edit Poin Tugas (REVISION)</h1>
            <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline" class="h-10 px-4">
                Kembali
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <div class="text-sm text-slate-600">
                Tugas: <b>{{ $task->title }}</b>
                <span class="text-slate-400">•</span>
                Periode: <b>{{ $task->period?->name ?? '-' }}</b>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">
                Edit ini hanya untuk koreksi poin pada periode <b>REVISION</b>.
            </div>

            <form method="POST" action="{{ route('kepala_unit.additional_tasks.update_points', $task->id) }}" class="grid gap-5 md:grid-cols-12">
                @csrf
                @method('PATCH')

                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Poin</label>
                    <x-ui.input type="number" name="points" step="1" min="0" :value="old('points', $task->points)" required />
                    @error('points')
                        <div class="mt-1 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="md:col-span-8">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Alasan (opsional)</label>
                    <x-ui.input name="reason" :value="old('reason')" placeholder="Contoh: Koreksi poin sesuai kebijakan revisi." />
                    @error('reason')
                        <div class="mt-1 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="md:col-span-12 flex items-center justify-end gap-3 pt-2">
                    <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline" class="h-11 px-5">
                        Batal
                    </x-ui.button>
                    <x-ui.button type="submit" variant="success" class="h-11 px-6">
                        Simpan Poin
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
