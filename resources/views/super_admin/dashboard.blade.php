<x-app-layout title="Dashboard Super Admin">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Super Admin</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        {{-- NOTIFICATIONS / REMINDERS (actionable only) --}}
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
                    @elseif($type==='success')
                        <div class="rounded-lg px-4 py-3 text-sm bg-emerald-50 text-emerald-800">
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
                    @elseif($type==='info')
                        <div class="rounded-lg px-4 py-3 text-sm bg-amber-50 text-amber-800">
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

        {{-- STAT CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total User" value="{{ $stats['total_user'] }}" icon="fa-users"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Unit Kerja" value="{{ $stats['total_unit'] }}" icon="fa-diagram-project"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Profesi" value="{{ $stats['total_profesi'] }}" icon="fa-user-doctor"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Email Belum Verifikasi" value="{{ $stats['unverified'] }}"
                         icon="fa-envelope-circle-check" accent="from-sky-500 to-indigo-600"/>
        </div>

        <x-section title="Perlu Perhatian">
            <div class="space-y-3">
                {{-- Site config ringkas --}}
                <div class="rounded-xl ring-1 ring-slate-100 p-4">
                    <div class="text-sm font-semibold text-slate-800">Konfigurasi Situs</div>
                    <div class="mt-1 text-sm text-slate-600">
                        @if(!$siteConfig['exists'])
                            Belum ada konfigurasi situs.
                        @elseif(!empty($siteConfig['missing']))
                            Belum lengkap: {{ implode(', ', $siteConfig['missing']) }}.
                        @else
                            Sudah lengkap.
                        @endif
                    </div>
                </div>

                {{-- Health checks ringkas (tampilkan status saja, detail ada di notifikasi bila bermasalah) --}}
                <div class="rounded-xl ring-1 ring-slate-100 p-4">
                    <div class="text-sm font-semibold text-slate-800">Kesehatan Sistem</div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">App Key</span>
                            <span class="text-xs px-2 py-1 rounded {{ $sysChecks['app_key'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $sysChecks['app_key'] ? 'OK' : 'Cek' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Database</span>
                            <span class="text-xs px-2 py-1 rounded {{ $sysChecks['database'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $sysChecks['database'] ? 'OK' : 'Cek' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Cache</span>
                            <span class="text-xs px-2 py-1 rounded {{ $sysChecks['cache'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $sysChecks['cache'] ? 'OK' : 'Cek' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Storage</span>
                            <span class="text-xs px-2 py-1 rounded {{ $sysChecks['storage_writable'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $sysChecks['storage_writable'] ? 'OK' : 'Cek' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-section>

        {{-- User Terbaru --}}
        <x-section title="User Terbaru">
            <div class="divide-y divide-slate-100">
                @forelse($recentUsers as $u)
                    <div class="py-2 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $u->name }}</div>
                            <div class="text-xs text-slate-500">{{ $u->email }} • {{ $u->role_label }}</div>
                        </div>
                        <div class="text-xs text-slate-500">{{ $u->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="py-4 text-sm text-slate-500">Belum ada data.</div>
                @endforelse
            </div>
        </x-section>
    </div>
</x-app-layout>
