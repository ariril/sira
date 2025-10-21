@php /** @var \App\Models\Announcement $announcement */ @endphp
<x-app-layout title="Edit Pengumuman">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Pengumuman</h1>
            <div class="flex items-center gap-2">
                <x-ui.button as="a" href="{{ route('announcements.show', $announcement->slug) }}" target="_blank" variant="outline">
                    <i class="fa-solid fa-up-right-from-square"></i> Lihat Publik
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.announcements.update', $announcement) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf @method('PUT')
            @include('super_admin.announcements._form')
        </form>
    </div>
</x-app-layout>
