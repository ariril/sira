<x-app-layout title="Buat Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Buat Tugas Tambahan</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('kepala_unit.additional-tasks.store') }}" class="grid md:grid-cols-2 gap-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" placeholder="(Opsional)" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Judul</label>
                    <x-ui.input name="title" required />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                    <x-ui.textarea name="description" rows="4" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Mulai</label>
                    <x-ui.input type="date" name="start_date" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                    <x-ui.input type="date" name="due_date" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Bonus (Rp)</label>
                    <x-ui.input type="number" step="0.01" name="bonus_amount" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Poin</label>
                    <x-ui.input type="number" step="0.01" name="points" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" value="1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status Awal</label>
                    <x-ui.select name="status" :options="['draft'=>'Draft','open'=>'Open','closed'=>'Closed','cancelled'=>'Cancelled']" :value="'open'" />
                </div>
                <div class="md:col-span-2 flex items-center justify-between pt-2">
                    <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </x-ui.button>
                    <x-ui.button type="submit" class="text-white bg-gradient-to-r from-amber-500 to-orange-600 hover:brightness-110 focus:ring-amber-500 border-0">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
