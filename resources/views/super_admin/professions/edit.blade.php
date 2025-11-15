<x-app-layout title="Edit Profesi">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Edit Profesi</h1>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <form action="{{ route('super_admin.professions.update', $profession) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                @include('super_admin.professions._form', [
                    'profession' => $profession,
                ])
            </form>
        </div>
    </div>
</x-app-layout>
