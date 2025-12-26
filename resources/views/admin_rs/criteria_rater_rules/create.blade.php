<x-app-layout title="Tambah Aturan Kriteria 360">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Tambah Aturan Kriteria 360</h1>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('admin_rs.criteria_rater_rules.store') }}" class="space-y-6">
                @csrf

                @include('admin_rs.criteria_rater_rules._form', [
                    'item' => $item,
                    'criteriaOptions' => $criteriaOptions,
                    'assessorTypes' => $assessorTypes,
                ])

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" variant="success">Simpan</x-ui.button>
                    <x-ui.button as="a" href="{{ route('admin_rs.criteria_rater_rules.index') }}" variant="outline">Batal</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
