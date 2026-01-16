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
                class="grid md:grid-cols-2 gap-5"
            >
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Periode
                        <span class="ml-1 text-amber-600 cursor-help" title="Periode diisi otomatis menggunakan periode penilaian yang aktif saat ini.">!</span>
                    </label>
                    <x-ui.input :value="($activePeriod->name ?? '-')" disabled />
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
