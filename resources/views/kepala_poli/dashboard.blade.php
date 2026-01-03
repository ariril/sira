<x-app-layout title="Dashboard Kepala Poliklinik">
    <x-slot name="header"><h1 class="text-2xl font-semibold">Dashboard Kepala Poliklinik</h1></x-slot>

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
            <x-stat-card label="Kinerja Unit" value="{{ ($stats['wsm'] ?? null) === null ? '—' : number_format((float) $stats['wsm'], 2) }}" icon="fa-scale-balanced" accent="from-fuchsia-500 to-purple-600"/>
            <x-stat-card label="Ulasan 30 Hari" value="{{ $stats['reviews_30d'] ?? 0 }}" icon="fa-comments" accent="from-fuchsia-500 to-purple-600"/>
            <x-stat-card label="Rata-Rata Rating" value="{{ ($stats['avg_rating'] ?? null) === null ? '—' : number_format((float) $stats['avg_rating'], 2) }}" icon="fa-star" accent="from-fuchsia-500 to-purple-600"/>
            <x-stat-card label="Penilaian Pending" value="{{ $stats['pending_assess'] ?? 0 }}" icon="fa-hourglass-half" accent="from-fuchsia-500 to-purple-600"/>
        </div>
    </div>
</x-app-layout>
