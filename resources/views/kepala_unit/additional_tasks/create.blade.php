<x-app-layout title="Buat Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Buat Tugas Tambahan</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form
                method="POST"
                action="{{ route('kepala_unit.additional-tasks.store') }}"
                enctype="multipart/form-data"
                class="grid md:grid-cols-2 gap-5"
                x-data="{
                    bonus: null,
                    points: null,
                    get bonusFilled() {
                        const v = Number(this.bonus);
                        return !Number.isNaN(v) && v > 0;
                    },
                    get pointsFilled() {
                        const v = Number(this.points);
                        return !Number.isNaN(v) && v > 0;
                    },
                    formattedBonus() {
                        const v = Number(this.bonus);
                        if (Number.isNaN(v) || v <= 0) return '-';
                        return new Intl.NumberFormat('id-ID').format(v);
                    },
                }"
            >
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" placeholder="Pilih periode" required />
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
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Mulai (WIB)</label>
                    <x-ui.input type="time" name="start_time" value="08:00" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                    <x-ui.input type="date" name="due_date" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Selesai (WIB)</label>
                    <x-ui.input type="time" name="due_time" value="23:59" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Bonus (Rp)
                        <span class="ml-1 text-amber-600 cursor-help" title="Nilai rupiah yang dibayarkan sebagai remunerasi. Tidak dapat diisi bersamaan dengan poin.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="bonus_amount" x-model.number="bonus" x-bind:disabled="pointsFilled" />
                    <p class="mt-1 text-xs text-slate-500">Nilai: Rp <span x-text="formattedBonus()"></span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Poin
                        <span class="ml-1 text-amber-600 cursor-help" title="Poin kinerja sebagai alternatif bonus. Tidak dapat diisi bersamaan dengan bonus.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="points" x-model.number="points" x-bind:disabled="bonusFilled" />
                    <p class="mt-1 text-xs text-slate-500">Rentang poin 0-100. Digunakan pada kriteria Kontribusi Tambahan.</p>
                </div>
                <div class="md:col-span-2 -mt-2">
                    <p class="text-xs text-slate-500">Isi salah satu: Bonus atau Poin. Semua waktu menggunakan zona Asia/Jakarta (UTC+7).</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">File Pendukung (Word/Excel/PPT)</label>
                    <input type="file" name="supporting_file" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                    <p class="mt-1 text-xs text-slate-500"> Maksimal 10 MB.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" value="1" />
                </div>
                <input type="hidden" name="status" value="open" />
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
