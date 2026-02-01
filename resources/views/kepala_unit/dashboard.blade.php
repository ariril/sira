<x-app-layout title="Dashboard Kepala Unit">
    <x-slot name="header"><h1 class="text-2xl font-semibold">Dashboard Kepala Unit</h1></x-slot>

    <div class="container-px py-6 space-y-6">
        @if(!empty($notifications))
            <div class="space-y-2">
                @foreach($notifications as $n)
                    @php($type = $n['type'] ?? 'info')
                    @php($href = $n['href'] ?? null)
                    @if($type==='warning')
                        <div class="rounded-lg px-4 py-3 text-sm bg-amber-50 text-amber-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if($href)
                                    <a href="{{ $href }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @elseif($type==='error')
                        <div class="rounded-lg px-4 py-3 text-sm bg-rose-50 text-rose-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if($href)
                                    <a href="{{ $href }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg px-4 py-3 text-sm bg-blue-50 text-blue-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if($href)
                                    <a href="{{ $href }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Anggota Unit" value="{{ $stats['members'] ?? 0 }}" icon="fa-users"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Penilaian Pending" value="{{ $stats['pending'] ?? 0 }}" icon="fa-hourglass-half"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Kinerja Rata-rata" value="{{ $stats['avg_wsm'] ?? '—' }}" icon="fa-gauge"
                         accent="from-amber-500 to-orange-600"/>
            <x-stat-card label="Tugas Tambahan" value="{{ $stats['add_tasks'] ?? 0 }}" icon="fa-list-check"
                         accent="from-amber-500 to-orange-600"/>
        </div>
    </div>
</x-app-layout>
