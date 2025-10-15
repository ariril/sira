<x-app-layout title="Dashboard Admin RS">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Admin RS</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- STAT CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="File Absensi Diunggah" value="{{ $stats['attendance_batches'] ?? 0 }}" icon="fa-file-excel" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Data Absensi" value="{{ $stats['attendances'] ?? 0 }}" icon="fa-calendar-check" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Penilaian Pending" value="{{ $stats['approvals_pending'] ?? 0 }}" icon="fa-list-check" accent="from-emerald-500 to-teal-600"/>
            <x-stat-card label="Unit Dapat Alokasi" value="{{ $stats['unit_allocations'] ?? 0 }}" icon="fa-diagram-project" accent="from-emerald-500 to-teal-600"/>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-section title="Status Approval Kinerja">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-500 border-b">
                    <tr>
                        <th class="py-2 text-left">Periode</th>
                        <th class="py-2 text-left">Pegawai</th>
                        <th class="py-2 text-left">Level</th>
                        <th class="py-2 text-left">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentApprovals as $approval)
                        <tr class="border-b">
                            <td class="py-2">{{ $approval->assessment->period->name ?? '-' }}</td>
                            <td>{{ $approval->assessment->user->name ?? '-' }}</td>
                            <td>{{ $approval->level }}</td>
                            <td>
                  <span class="px-2 py-1 rounded text-xs
                    @if($approval->status=='approved') bg-emerald-100 text-emerald-700
                    @elseif($approval->status=='rejected') bg-rose-100 text-rose-700
                    @else bg-amber-100 text-amber-700 @endif">
                    {{ ucfirst($approval->status) }}
                  </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-center text-slate-500">No approvals yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-section>

            <x-section title="Alokasi Remunerasi Terbaru">
                <ul class="divide-y text-sm">
                    @forelse($recentAllocations as $alloc)
                        <li class="py-2 flex justify-between">
                            <div>
                                <div class="font-medium">{{ $alloc->unit->name }}</div>
                                <div class="text-xs text-slate-500">{{ $alloc->period->name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ number_format($alloc->amount, 2) }}</div>
                                <div class="text-xs text-slate-500">{{ $alloc->published_at ? 'Published' : 'Draft' }}</div>
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
