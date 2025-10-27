<x-app-layout title="Ubah Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Ubah Tugas Tambahan</h1>
            <div class="flex items-center gap-2">
                <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" class="h-10 px-4 text-sm">Kembali</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('kepala_unit.additional-tasks.update', $item->id) }}" class="grid md:grid-cols-2 gap-5">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="$item->assessment_period_id" placeholder="(Opsional)" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Judul</label>
                    <x-ui.input name="title" :value="$item->title" required />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                    <x-ui.textarea name="description" rows="4">{{ $item->description }}</x-ui.textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Mulai</label>
                    <x-ui.input type="date" name="start_date" :value="\Carbon\Carbon::parse($item->start_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                    <x-ui.input type="date" name="due_date" :value="\Carbon\Carbon::parse($item->due_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Bonus (Rp)</label>
                    <x-ui.input type="number" step="0.01" name="bonus_amount" :value="$item->bonus_amount" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Poin</label>
                    <x-ui.input type="number" step="0.01" name="points" :value="$item->points" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" :value="$item->max_claims" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['draft'=>'Draft','open'=>'Open','closed'=>'Closed','cancelled'=>'Cancelled']" :value="$item->status" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <x-ui.button type="submit" class="h-10 px-6">Simpan</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
