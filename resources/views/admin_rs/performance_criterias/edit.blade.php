<x-app-layout title="Edit Kriteria Kinerja">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Kriteria</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.performance-criterias.index') }}" variant="outline" class="h-12 px-6 text-base">
                Kembali
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="POST" action="{{ route('admin_rs.performance-criterias.update', $item) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @if ($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @include('admin_rs.performance_criterias._form', [
                    'item' => $item,
                    'types' => $types,
                    'normalizationBases' => $normalizationBases,
                    'hasOtherCriteria' => $hasOtherCriteria,
                ])

                <div class="flex justify-end gap-3">
                    <x-ui.button as="a" href="{{ route('admin_rs.performance-criterias.index') }}" variant="outline">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="success">Update</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
