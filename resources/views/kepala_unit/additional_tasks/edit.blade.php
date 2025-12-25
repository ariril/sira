<x-app-layout title="Ubah Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Ubah Tugas Tambahan</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form
                method="POST"
                action="{{ route('kepala_unit.additional-tasks.update', $item->id) }}"
                enctype="multipart/form-data"
                class="grid md:grid-cols-2 gap-5"
                x-data="{ bonus: @js($item->bonus_amount), points: @js($item->points) }"
            >
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="$item->assessment_period_id" placeholder="Pilih periode" required />
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
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Mulai (WIB)</label>
                    <x-ui.input type="time" name="start_time" :value="$item->start_time ? \Carbon\Carbon::parse($item->start_time)->format('H:i') : '08:00'" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                    <x-ui.input type="date" name="due_date" :value="\Carbon\Carbon::parse($item->due_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Selesai (WIB)</label>
                    <x-ui.input type="time" name="due_time" :value="$item->due_time ? \Carbon\Carbon::parse($item->due_time)->format('H:i') : '23:59'" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Bonus (Rp)
                        <span class="ml-1 text-amber-600 cursor-help" title="Nilai rupiah remunerasi. Tidak dapat diisi bersamaan dengan poin.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="bonus_amount" :value="$item->bonus_amount" x-model="bonus" x-bind:disabled="points && points > 0" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Poin
                        <span class="ml-1 text-amber-600 cursor-help" title="Poin kinerja sebagai alternatif bonus. Tidak dapat diisi bersamaan dengan bonus.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="points" :value="$item->points" x-model="points" x-bind:disabled="bonus && bonus > 0" />
                </div>
                <div class="md:col-span-2 -mt-2">
                    <p class="text-xs text-slate-500">Isi salah satu: Bonus atau Poin. Semua waktu menggunakan zona Asia/Jakarta (UTC+7).</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">File Pendukung (Word/Excel/PPT/PDF)</label>
                    <input type="file" name="supporting_file" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                    @if($item->policy_doc_path)
                        <p class="mt-1 text-xs text-slate-500">File saat ini: <a href="{{ asset('storage/'.$item->policy_doc_path) }}" class="text-amber-600 hover:underline" target="_blank">Download</a></p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" :value="$item->max_claims" />
                </div>
                <div class="md:col-span-2 flex items-center justify-between pt-2">
                    <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </x-ui.button>
                    <x-ui.button type="submit" variant="orange" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
