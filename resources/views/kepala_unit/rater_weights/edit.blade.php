<x-app-layout title="Edit Bobot Penilai 360">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Bobot Penilai 360</h1>
            <x-ui.button as="a" href="{{ route('kepala_unit.rater_weights.index') }}" variant="outline">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            @if(session('status'))
                <div class="mb-4 p-4 rounded-xl border text-sm bg-emerald-50 border-emerald-200 text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-4 rounded-xl border text-sm bg-rose-50 border-rose-200 text-rose-800">
                    <div class="font-semibold">Periksa kembali input Anda.</div>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('kepala_unit.rater_weights.update', $item) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('kepala_unit.rater_weights._form')

                <div class="flex justify-end gap-2">
                    <x-ui.button type="submit" variant="orange" class="h-10 px-6">Simpan Draft</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
