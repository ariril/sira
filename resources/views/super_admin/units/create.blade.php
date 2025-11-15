<x-app-layout title="Tambah Unit">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Tambah Unit</h1>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <form action="{{ route('super_admin.units.store') }}" method="POST" class="space-y-4">
                @csrf
                @include('super_admin.units._form', [
                    'unit'       => $unit,
                    'types'      => $types, // map value => label
                    'submitText' => 'Simpan',
                ])
            </form>
        </div>
    </div>
</x-app-layout>
