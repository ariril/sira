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
                    <x-ui.textarea name="description" rows="4" :value="$item->description" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Ketentuan Tambahan (PDF)</label>
                    @if(!empty($item->policy_doc_path))
                        <div class="mb-2 text-sm">
                            <a class="text-sky-700 hover:underline" target="_blank" href="{{ asset('storage/' . ltrim($item->policy_doc_path, '/')) }}">
                                Lihat PDF saat ini
                            </a>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 mb-2">
                            <input type="checkbox" name="remove_policy_doc" value="1" class="rounded border-slate-300" />
                            Hapus PDF saat ini
                        </label>
                    @endif

                    <input
                        type="file"
                        name="policy_doc"
                        accept="application/pdf"
                        class="mt-1 block w-full text-sm text-slate-700 border border-slate-200 rounded-xl bg-white p-2 focus:outline-none focus:ring-2 focus:ring-sky-200 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100"
                    />
                    <p class="mt-1 text-xs text-slate-500">Opsional. Upload file baru untuk mengganti. Maks 10MB. Format: PDF.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Jatuh Tempo</label>
                    <x-ui.input type="date" name="due_date" :value="\Carbon\Carbon::parse($item->due_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Jatuh Tempo (WIB)</label>
                    <x-ui.input type="time" name="due_time" :value="$item->due_time ? \Carbon\Carbon::parse($item->due_time)->format('H:i') : '23:59'" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Poin
                        <span class="ml-1 text-amber-600 cursor-help" title="Poin yang akan diberikan jika klaim disetujui.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="points" :value="$item->points" required />
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
