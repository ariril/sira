<x-app-layout title="Dashboard Admin RS">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Admin RS</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- NOTIFICATIONS / REMINDERS --}}
        @if(!empty($notifications))
            <div class="space-y-2">
                @foreach($notifications as $n)
                    @php($type = $n['type'] ?? 'info')
                    @if($type==='warning')
                        <div class="rounded-lg px-4 py-3 text-sm bg-amber-50 text-amber-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if(!empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @elseif($type==='success')
                        <div class="rounded-lg px-4 py-3 text-sm bg-emerald-50 text-emerald-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if(!empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @elseif($type==='error')
                        <div class="rounded-lg px-4 py-3 text-sm bg-rose-50 text-rose-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if(!empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @elseif($type==='info')
                        <div class="rounded-lg px-4 py-3 text-sm bg-amber-50 text-amber-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if(!empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg px-4 py-3 text-sm bg-blue-50 text-blue-800">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ $n['text'] ?? '' }}</span>
                                @if(!empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="underline font-medium">Lihat</a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
        {{-- STAT CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="File Absensi Diunggah" value="{{ $stats['attendance_batches'] ?? 0 }}" icon="fa-file-excel" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Data Absensi" value="{{ $stats['attendances'] ?? 0 }}" icon="fa-calendar-check" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Penilaian Pending (Lv.1)" value="{{ $stats['approvals_pending_l1'] ?? 0 }}" icon="fa-list-check" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Unit Dapat Alokasi" value="{{ $stats['unit_allocations'] ?? 0 }}" icon="fa-diagram-project" accent="from-emerald-500 to-teal-600"/>
        </div>

        {{-- Section dihapus sesuai permintaan; fokus pada notifikasi di atas dan kartu statistik --}}
    </div>
</x-app-layout>
