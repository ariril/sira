<x-app-layout title="Tambah Pengguna">
    <x-slot name="header"><h1 class="text-2xl font-semibold">Tambah Pengguna</h1></x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <form action="{{ route('super_admin.users.store') }}" method="POST" class="space-y-4">
                @csrf
                @include('super_admin.users._form', [
                    'user'        => $user,
                    'roles'       => $roles,
                    'units'       => $units,
                    'professions' => $professions,
                ])
            </form>
        </div>
    </div>
</x-app-layout>
