<x-app-layout title="Edit Unit">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Edit Unit</h1>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <form action="{{ route('super_admin.units.update', $unit) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                @include('super_admin.units._form', [
                    'unit'       => $unit,
                    'types'      => $types, // map value => label
                    'submitText' => 'Create',
                ])
            </form>
        </div>
    </div>
</x-app-layout>
