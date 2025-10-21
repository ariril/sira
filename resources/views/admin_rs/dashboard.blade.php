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
                    @elseif($type==='error')
                        <div class="rounded-lg px-4 py-3 text-sm bg-rose-50 text-rose-800">
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
            <x-stat-card label="Penilaian Pending" value="{{ $stats['approvals_pending'] ?? 0 }}" icon="fa-list-check" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Unit Dapat Alokasi" value="{{ $stats['unit_allocations'] ?? 0 }}" icon="fa-diagram-project" accent="from-emerald-500 to-teal-600"/>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-section title="Status Approval Kinerja">
                <table class="min-w-full">
                    <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Pegawai</th>
                        <th class="px-6 py-4 text-left">Level</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($recentApprovals as $approval)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $approval->period_name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $approval->user_name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $approval->level ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @php($st = $approval->status ?? 'pending')
                                @if($st==='approved')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">{{ ucfirst($st) }}</span>
                                @elseif($st==='rejected')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">{{ ucfirst($st) }}</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">{{ ucfirst($st) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No approvals yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-section>

            <x-section title="Alokasi Remunerasi Terbaru">
                <ul class="divide-y text-sm">
                    @forelse($recentAllocations as $alloc)
                        <li class="py-2 flex justify-between">
                            <div>
                                <div class="font-medium">{{ $alloc->unit_name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $alloc->period_name ?? '-' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ isset($alloc->amount) ? number_format((float)$alloc->amount, 2) : '-' }}</div>
                                <div class="text-xs text-slate-500">{{ !empty($alloc->published_at) ? 'Published' : 'Draft' }}</div>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-center text-slate-500">No data yet.</li>
                    @endforelse
                </ul>
            </x-section>
        </div>
    </div>
</x-app-layout>
