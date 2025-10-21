<x-app-layout title="Buat Pengumuman">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Buat Pengumuman</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.announcements.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf
            @include('super_admin.announcements._form', ['announcement' => $announcement ?? new \App\Models\Announcement()])
        </form>
    </div>
</x-app-layout>
