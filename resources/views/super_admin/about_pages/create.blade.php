<x-app-layout title="Buat Halaman Tentang">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Buat Halaman Tentang</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.about-pages.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf
            @include('super_admin.about_pages._form', ['aboutPage' => $aboutPage ?? new \App\Models\AboutPage(), 'types' => $types])
        </form>
    </div>
</x-app-layout>
