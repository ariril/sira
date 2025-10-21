@php /** @var \App\Models\Faq $faq */ @endphp
<x-app-layout title="Edit FAQ">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit FAQ</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.faqs.update', $faq) }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf @method('PUT')
            @include('super_admin.faqs._form')
        </form>
    </div>
</x-app-layout>
