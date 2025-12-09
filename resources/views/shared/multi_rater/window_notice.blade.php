@props(['window' => null, 'periodName' => null])

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
    @if($window)
        @php
            $startAt = optional($window->start_date)?->copy()->startOfDay();
            $endAt = optional($window->end_date)?->copy()->endOfDay();
        @endphp
        <div class="text-sm text-slate-700">
            Penilaian 360 dimulai dari <b>{{ optional($window->start_date)->format('d M Y') }}</b>
            hingga <b>{{ optional($window->end_date)->format('d M Y') }}</b>
            pada periode <b>{{ $periodName ?? optional($window->period)->name ?? '-' }}</b>.
            @if($startAt && $endAt)
                @if(now()->lt($startAt))
                    <span class="inline-flex ml-2 text-amber-700 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded">Periode belum dibuka.</span>
                @elseif(now()->between($startAt, $endAt, true))
                    <span class="inline-flex ml-2 text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded">Anda dapat mengisi penilaian sekarang.</span>
                @else
                    <span class="inline-flex ml-2 text-slate-600 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">Periode sudah berakhir.</span>
                @endif
            @endif
        </div>
    @else
        <div class="text-sm text-amber-700 bg-amber-50 border border-amber-100 px-3 py-2 rounded">
            Penilaian 360 belum dibuka.
        </div>
    @endif
</div>
