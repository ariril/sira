<x-app-layout title="Edit Periode Penilaian">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Periode</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.assessment-periods.index') }}" variant="outline" class="h-12 px-6 text-base">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="POST" action="{{ route('admin_rs.assessment-periods.update', $item) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Periode</label>
                        <x-ui.input name="name" :value="old('name', $item->name)" required />
                        @error('name')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Siklus</label>
                        <x-ui.input name="cycle" :value="old('cycle', $item->cycle)" placeholder="Mis. Triwulan III 2025" />
                        @error('cycle')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Mulai</label>
                        <x-ui.input type="date" name="start_date" :value="old('start_date', optional($item->start_date)->format('Y-m-d'))" required />
                        @error('start_date')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selesai</label>
                        <x-ui.input type="date" name="end_date" :value="old('end_date', optional($item->end_date)->format('Y-m-d'))" required />
                        @error('end_date')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <x-ui.input type="checkbox" name="is_active" value="1" :checked="old('is_active', $item->is_active)" class="h-5 w-5" />
                    <div>
                        <div class="text-sm font-medium text-slate-700">Aktifkan periode ini</div>
                        <div class="text-xs text-slate-500">Hanya satu periode yang aktif dalam satu waktu.</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button as="a" href="{{ route('admin_rs.assessment-periods.index') }}" variant="outline">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="success">Update</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
