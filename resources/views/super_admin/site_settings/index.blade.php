<x-app-layout title="Pengaturan Situs">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Pengaturan Situs</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.site-settings.update') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf
            @method('PUT')
            @include('super_admin.site_settings._form', ['setting' => $setting])
        </form>
    </div>
</x-app-layout>
