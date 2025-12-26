<x-app-layout title="Aturan Kriteria 360">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Aturan Kriteria 360</h1>
            <x-ui.button variant="success" as="a" href="{{ route('admin_rs.criteria_rater_rules.create') }}">Tambah Aturan</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-12 items-end">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria 360</label>
                    <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" :value="request('performance_criteria_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tipe Penilai</label>
                    <x-ui.select name="assessor_type" :options="$assessorTypes" :value="request('assessor_type')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <x-ui.button type="submit" variant="success" class="w-full">Filter</x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
            <x-ui.table min-width="720px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </x-slot>

                @forelse($items as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800">{{ $row->performanceCriteria?->name ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-slate-700">{{ $assessorTypes[$row->assessor_type] ?? $row->assessor_type }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                <x-ui.button as="a" href="{{ route('admin_rs.criteria_rater_rules.edit', $row) }}" variant="outline" class="h-10 px-4">Edit</x-ui.button>
                                <form method="POST" action="{{ route('admin_rs.criteria_rater_rules.destroy', $row) }}" onsubmit="return confirm('Hapus aturan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="danger" class="h-10 px-4">Hapus</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada aturan.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
