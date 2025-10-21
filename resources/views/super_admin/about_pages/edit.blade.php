@php /** @var \App\Models\AboutPage $aboutPage */ @endphp
<x-app-layout title="Edit About Page">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit About Page</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.about-pages.update', $aboutPage) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf @method('PUT')
            @include('super_admin.about_pages._form')
        </form>
    </div>
</x-app-layout>
