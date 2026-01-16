<x-app-layout title="Buat Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Buat Tugas Tambahan</h1>
        </div>
    </x-slot>

    @php
        $tz = config('app.timezone');
        $nowTz = \Illuminate\Support\Carbon::now($tz);
        $today = $nowTz->toDateString();
        $timeNow = $nowTz->format('H:i');
    @endphp

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form
                method="POST"
                action="{{ route('kepala_unit.additional-tasks.store') }}"
                enctype="multipart/form-data"
                class="grid md:grid-cols-2 gap-5"
            >
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Periode
                        <span class="ml-1 text-amber-600 cursor-help" title="Periode diisi otomatis menggunakan periode penilaian yang aktif saat ini.">!</span>
                    </label>
                    <x-ui.select
                        name="assessment_period_id"
                        :options="($periods ?? collect())->pluck('name','id')"
                        :value="old('assessment_period_id', $activePeriod?->id)"
                        placeholder="Pilih periode"
                        required
                    />
                    @unless($activePeriod)
                        <p class="mt-2 text-xs text-rose-600">Tidak ada periode yang aktif. Hubungi Admin RS.</p>
                    @endunless
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Judul</label>
                    <x-ui.input name="title" required />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                    <x-ui.textarea name="description" rows="4" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Ketentuan Tambahan (PDF)</label>
                    <input
                        type="file"
                        name="policy_doc"
                        accept="application/pdf"
                        class="mt-1 block w-full text-sm text-slate-700 border border-slate-200 rounded-xl bg-white p-2 focus:outline-none focus:ring-2 focus:ring-sky-200 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
                    />
                    <p class="mt-1 text-xs text-slate-500">Opsional. Maks 10MB. Format: PDF.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Jatuh Tempo</label>
                    <x-ui.input type="date" name="due_date" :value="old('due_date', $today)" :min="$today" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Jatuh Tempo (WIB)</label>
                    <x-ui.input type="time" name="due_time" :value="old('due_time', '23:59')" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Poin
                        <span class="ml-1 text-amber-600 cursor-help" title="Poin yang akan diberikan jika klaim disetujui.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="points" :value="old('points')" required />
                    <p class="mt-1 text-xs text-slate-500">Semua waktu mengikuti zona: {{ $tz }}.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" :value="old('max_claims', 1)" />
                </div>
                <div class="md:col-span-2 flex items-center justify-between pt-2">
                    <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </x-ui.button>
                    <x-ui.button type="submit" variant="orange" class="h-12 px-6 text-base" :disabled="!$activePeriod">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
