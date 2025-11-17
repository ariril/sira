<x-app-layout title="Detail Absensi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Absensi</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.index') }}" class="h-10 px-4 text-sm">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <dl class="grid md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                <div>
                    <dt class="text-slate-500">Nama</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">NIP</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->employee_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Unit</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->user->unit->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Tanggal</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->attendance_date?->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Masuk</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->check_in ? \Carbon\Carbon::parse($item->check_in)->format('Y-m-d H:i') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Pulang</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->check_out ? \Carbon\Carbon::parse($item->check_out)->format('Y-m-d H:i') : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Sumber</dt>
                    <dd class="text-slate-800 font-medium">{{ $item->source?->value }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('admin_rs.attendances.update', $item) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                        <x-ui.select name="attendance_status" :options="$statuses" :value="$item->attendance_status?->value" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Catatan</label>
                        <x-ui.input name="overtime_note" value="{{ $item->overtime_note }}" />
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Masuk (Y-m-d H:i)</label>
                        <x-ui.input name="check_in" value="{{ $item->check_in ? \Carbon\Carbon::parse($item->check_in)->format('Y-m-d H:i') : '' }}" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Pulang (Y-m-d H:i)</label>
                        <x-ui.input name="check_out" value="{{ $item->check_out ? \Carbon\Carbon::parse($item->check_out)->format('Y-m-d H:i') : '' }}" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" class="h-10 px-5">Simpan</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
