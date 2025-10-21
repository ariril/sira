<x-app-layout title="Buat FAQ">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Buat FAQ</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form action="{{ route('super_admin.faqs.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @csrf
            @include('super_admin.faqs._form', ['faq' => $faq ?? new \App\Models\Faq()])
        </form>
    </div>
</x-app-layout>
