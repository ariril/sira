<x-app-layout title="Edit Alokasi Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Alokasi</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.index') }}" variant="outline" class="h-12 px-6 text-base">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.update', $item) }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Periode</label>
                        <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="old('assessment_period_id', $item->assessment_period_id)" placeholder="Pilih periode" />
                        @error('assessment_period_id')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit</label>
                        <x-ui.select name="unit_id" :options="$units->pluck('name','id')" :value="old('unit_id', $item->unit_id)" placeholder="Pilih unit" />
                        @error('unit_id')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah</label>
                        <x-ui.input name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $item->amount) }}" />
                        @error('amount')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                    <x-ui.input as="textarea" name="note" rows="4" value="{{ old('note', $item->note) }}" placeholder="Opsional" />
                    @error('note')<div class="text-rose-600 text-xs mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.input type="checkbox" name="publish_now" value="1" class="h-5 w-5" :checked="!empty($item->published_at)" />
                    <div>
                        <div class="text-sm font-medium text-slate-700">Published</div>
                        <div class="text-xs text-slate-500">Centang untuk publish, hapus centang untuk draft.</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.index') }}" variant="outline">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="success">Update</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
