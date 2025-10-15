<x-app-layout title="Dashboard Kepala Unit">
    <x-slot name="header"><h1 class="text-2xl font-semibold">Dashboard Kepala Unit</h1></x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Anggota Unit" value="{{ $stats['members'] ?? 0 }}" icon="fa-users"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Penilaian Pending" value="{{ $stats['pending'] ?? 0 }}" icon="fa-hourglass-half"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Nilai WSM Rata2" value="{{ number_format($stats['avg_wsm'] ?? 0,2) }}" icon="fa-gauge"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Kontribusi Tambahan" value="{{ $stats['add_tasks'] ?? 0 }}" icon="fa-list-check"
                         accent="from-amber-500 to-orange-600"/>
        </div>

        <x-section title="Validasi Kontribusi">
            {{-- tabel kontribusi menunggu validasi --}}
            {{ $contribTable ?? 'Tidak ada data' }}
        </x-section>
    </div>
</x-app-layout>
