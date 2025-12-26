<x-app-layout title="Tambah Bobot Penilai 360">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Tambah Bobot Penilai 360</h1>
            <x-ui.button as="a" href="{{ route('kepala_unit.rater_weights.index') }}" variant="outline">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('kepala_unit.rater_weights.store') }}" class="space-y-6">
                @csrf

                @include('kepala_unit.rater_weights._form')

                <div class="flex justify-end gap-2">
                    <x-ui.button type="submit" variant="orange" class="h-10 px-6">Simpan Draft</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
