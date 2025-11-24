<x-app-layout title="Dashboard Super Admin">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Super Admin</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">

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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Distribusi Pengguna --}}
            <x-section title="Distribusi Pengguna">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-slate-500">Pegawai Medis</dt>
                        <dd class="text-lg font-semibold">{{ $userDistribution['pegawai_medis'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Kepala Unit</dt>
                        <dd class="text-lg font-semibold">{{ $userDistribution['kepala_unit'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Kepala Poliklinik</dt>
                        <dd class="text-lg font-semibold">{{ $userDistribution['kepala_poliklinik'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Admin RS</dt>
                        <dd class="text-lg font-semibold">{{ $userDistribution['admin_rs'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Super Admin</dt>
                        <dd class="text-lg font-semibold">{{ $userDistribution['super_admin'] }}</dd>
                    </div>
                </dl>
            </x-section>

            {{-- Kesehatan Sistem --}}
            <x-section title="Kesehatan Sistem">
                <ul class="divide-y divide-slate-100">
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-600">App Key</span>
                        <span class="text-xs px-2 py-1 rounded {{ $sysChecks['app_key'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $sysChecks['app_key'] ? 'OK' : 'Cek' }}
                        </span>
                    </li>
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-600">Database</span>
                        <span class="text-xs px-2 py-1 rounded {{ $sysChecks['database'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $sysChecks['database'] ? 'OK' : 'Cek' }}
                        </span>
                    </li>
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-600">Cache</span>
                        <span class="text-xs px-2 py-1 rounded {{ $sysChecks['cache'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $sysChecks['cache'] ? 'OK' : 'Cek' }}
                        </span>
                    </li>
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-600">Storage Writable</span>
                        <span class="text-xs px-2 py-1 rounded {{ $sysChecks['storage_writable'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $sysChecks['storage_writable'] ? 'OK' : 'Cek' }}
                        </span>
                    </li>
                </ul>
                <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500">
                    <div>Env: <span class="font-medium text-slate-700">{{ $sysSummary['env'] }}</span></div>
                    <div>Debug: <span class="font-medium text-slate-700">{{ $sysSummary['debug'] ? 'on' : 'off' }}</span></div>
                    <div>PHP: <span class="font-medium text-slate-700">{{ $sysSummary['php'] }}</span></div>
                    <div>Laravel: <span class="font-medium text-slate-700">{{ $sysSummary['laravel'] }}</span></div>
                    <div>Timezone: <span class="font-medium text-slate-700">{{ $sysSummary['timezone'] }}</span></div>
                    <div>Queue: <span class="font-medium text-slate-700">{{ $sysSummary['queue'] }}</span></div>
                </div>
            </x-section>

            {{-- Konfigurasi Situs --}}
            <x-section title="Konfigurasi Situs">
                @if(!$siteConfig['exists'])
                    <p class="text-sm text-slate-500">Belum ada konfigurasi situs. Silakan lengkapi pengaturan.</p>
                @else
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-slate-500">Nama Situs</dt>
                            <dd class="text-lg font-semibold">{{ $siteConfig['name'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Email</dt>
                            <dd class="text-lg font-semibold break-all">{{ $siteConfig['email'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Alamat</dt>
                            <dd class="text-sm font-medium text-slate-700">{{ $siteConfig['address'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Logo</dt>
                            <dd class="text-sm font-medium">{{ $siteConfig['logo'] ? 'Tersedia' : 'Belum diunggah' }}</dd>
                        </div>
                    </dl>
                    @if(!empty($siteConfig['missing']))
                        <div class="mt-4 text-xs text-amber-700 bg-amber-50 rounded px-3 py-2">
                            Perlu dilengkapi: {{ implode(', ', $siteConfig['missing']) }}
                        </div>
                    @endif
                @endif
            </x-section>
        </div>

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
