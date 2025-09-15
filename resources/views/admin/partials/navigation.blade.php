@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $role = $user?->role;
    $site = \App\Models\SiteSetting::first();

    $href = function (string $routeName, string $fallbackPath) {
        return Route::has($routeName) ? route($routeName) : url($fallbackPath);
    };

    /* ===========================
       MENU: SUPER ADMIN (global)
       =========================== */
    $menuSuperAdmin = [
        [
            'heading' => 'Dashboard',
            'items' => [
                ['label'=>'Dashboard','icon'=>'fa-gauge',
                 'href'=>$href('super_admin.dashboard','/super-admin/dashboard'),
                 'active'=>request()->routeIs('super_admin.dashboard')],
            ],
        ],
        [
            'heading' => 'Master Data',
            'items' => [
                ['label'=>'User','icon'=>'fa-users',
                 'href'=>$href('super_admin.users.index','/super-admin/users'),
                 'active'=>request()->routeIs('super_admin.users.*')],
                ['label'=>'Unit Kerja','icon'=>'fa-diagram-project',
                 'href'=>$href('super_admin.unit_kerja.index','/super-admin/unit-kerja'),
                 'active'=>request()->routeIs('super_admin.unit_kerja.*')],
                ['label'=>'Profession','icon'=>'fa-user-doctor',
                 'href'=>$href('super_admin.profesi.index','/super-admin/profesi'),
                 'active'=>request()->routeIs('super_admin.profesi.*')],
            ],
        ],
        [
            'heading' => 'Kinerja',
            'items' => [
                ['label'=>'Kriteria Kinerja','icon'=>'fa-list-check',
                 'href'=>$href('super_admin.kriteria.index','/super-admin/kriteria-kinerja'),
                 'active'=>request()->routeIs('super_admin.kriteria.*')],
                ['label'=>'Bobot per Unit','icon'=>'fa-scale-balanced',
                 'href'=>$href('super_admin.bobot.index','/super-admin/bobot-kriteria-unit'),
                 'active'=>request()->routeIs('super_admin.bobot.*')],
                ['label'=>'Periode Penilaian','icon'=>'fa-calendar-days',
                 'href'=>$href('super_admin.periode.index','/super-admin/periode-penilaian'),
                 'active'=>request()->routeIs('super_admin.periode.*')],
                // monitoring (input & approval tetap dikerjakan role lain)
                ['label'=>'Monitoring Penilaian','icon'=>'fa-eye',
                 'href'=>$href('super_admin.monitoring.penilaian','/super-admin/monitoring/penilaian'),
                 'active'=>request()->is('super-admin/monitoring/penilaian*')],
            ],
        ],
        [
            'heading' => 'Attendance',
            'items' => [
                ['label'=>'Impor Excel','icon'=>'fa-file-arrow-up',
                 'href'=>$href('super_admin.kehadiran.import','/super-admin/kehadiran/import'),
                 'active'=>request()->is('super-admin/kehadiran/import*')],
                ['label'=>'Rekap Attendance','icon'=>'fa-calendar-check',
                 'href'=>$href('super_admin.kehadiran.index','/super-admin/kehadiran'),
                 'active'=>request()->is('super-admin/kehadiran*')],
            ],
        ],
        [
            'heading' => 'Remuneration',
            'items' => [
                ['label'=>'Perhitungan & Publikasi','icon'=>'fa-money-bill-trend-up',
                 'href'=>$href('super_admin.remunerasi.index','/super-admin/remunerasi'),
                 'active'=>request()->routeIs('super_admin.remunerasi.*')],
                ['label'=>'Laporan Remuneration','icon'=>'fa-file-invoice',
                 'href'=>$href('super_admin.remunerasi.report','/super-admin/remunerasi/laporan'),
                 'active'=>request()->is('super-admin/remunerasi/laporan*')],
            ],
        ],
        [
            'heading' => 'Layanan Klinik',
            'items' => [
                ['label'=>'Jadwal Dokter','icon'=>'fa-business-time',
                 'href'=>$href('super_admin.jadwal.index','/super-admin/jadwal-dokter'),
                 'active'=>request()->is('super-admin/jadwal-dokter*')],
                ['label'=>'Antrian Pasien','icon'=>'fa-clipboard-list',
                 'href'=>$href('super_admin.antrian.index','/super-admin/antrian'),
                 'active'=>request()->is('super-admin/antrian*')],
                ['label'=>'Visit','icon'=>'fa-id-card-clip',
                 'href'=>$href('super_admin.kunjungan.index','/super-admin/kunjungan'),
                 'active'=>request()->is('super-admin/kunjungan*')],
                ['label'=>'Petugas per Visit','icon'=>'fa-user-nurse',
                 'href'=>$href('super_admin.kunjungan_tm.index','/super-admin/kunjungan/tenaga-medis'),
                 'active'=>request()->is('super-admin/kunjungan/tenaga-medis*')],
                ['label'=>'Transaksi Pembayaran','icon'=>'fa-cash-register',
                 'href'=>$href('super_admin.transaksi.index','/super-admin/transaksi'),
                 'active'=>request()->is('super-admin/transaksi*')],
            ],
        ],
        [
            'heading' => 'Feedback Pasien',
            'items' => [
                ['label'=>'Review Pasien','icon'=>'fa-comments',
                 'href'=>$href('super_admin.ulasan.index','/super-admin/ulasan'),
                 'active'=>request()->routeIs('super_admin.ulasan.*')],
            ],
        ],
        [
            'heading' => 'Konten Situs',
            'items' => [
                ['label'=>'Announcement','icon'=>'fa-bullhorn',
                 'href'=>$href('super_admin.pengumuman.index','/super-admin/konten/pengumuman'),
                 'active'=>request()->routeIs('super_admin.pengumuman.*')],
                ['label'=>'FAQ','icon'=>'fa-circle-question',
                 'href'=>$href('super_admin.faq.index','/super-admin/konten/faq'),
                 'active'=>request()->routeIs('super_admin.faq.*')],
                ['label'=>'Halaman Tentang','icon'=>'fa-file-lines',
                 'href'=>$href('super_admin.tentang.index','/super-admin/konten/tentang'),
                 'active'=>request()->routeIs('super_admin.tentang.*')],
                ['label'=>'Pengaturan Situs','icon'=>'fa-gear',
                 'href'=>$href('super_admin.pengaturan.index','/super-admin/pengaturan-situs'),
                 'active'=>request()->routeIs('super_admin.pengaturan.*')],
            ],
        ],
        [
            'heading' => 'Laporan & Ekspor',
            'items' => [
                ['label'=>'Semua Laporan','icon'=>'fa-table',
                 'href'=>$href('super_admin.laporan.index','/super-admin/laporan'),
                 'active'=>request()->routeIs('super_admin.laporan.*')],
            ],
        ],
    ];

    /* =============================================
       MENU: ADMINISTRASI (operasional poliklinik)
       ============================================= */
    $menuAdministrasi = [
        [
            'heading' => 'Dashboard',
            'items' => [
                ['label'=>'Dashboard','icon'=>'fa-gauge',
                 'href'=>$href('administrasi.dashboard','/administrasi/dashboard'),
                 'active'=>request()->routeIs('administrasi.dashboard')],
            ],
        ],
        [
            'heading' => 'Operasional Poli',
            'items' => [
                ['label'=>'Jadwal Dokter','icon'=>'fa-business-time',
                 'href'=>$href('administrasi.jadwal.index','/administrasi/jadwal-dokter'),
                 'active'=>request()->is('administrasi/jadwal-dokter*')],
                ['label'=>'Antrian Pasien','icon'=>'fa-clipboard-list',
                 'href'=>$href('administrasi.antrian.index','/administrasi/antrian'),
                 'active'=>request()->is('administrasi/antrian*')],
                ['label'=>'Visit','icon'=>'fa-id-card-clip',
                 'href'=>$href('administrasi.kunjungan.index','/administrasi/kunjungan'),
                 'active'=>request()->is('administrasi/kunjungan*')],
                ['label'=>'Transaksi Pembayaran','icon'=>'fa-cash-register',
                 'href'=>$href('administrasi.transaksi.index','/administrasi/transaksi'),
                 'active'=>request()->is('administrasi/transaksi*')],
            ],
        ],
        [
            'heading' => 'Feedback',
            'items' => [
                ['label'=>'Review Pasien','icon'=>'fa-comments',
                 'href'=>$href('administrasi.ulasan.index','/administrasi/ulasan'),
                 'active'=>request()->routeIs('administrasi.ulasan.*')],
            ],
        ],
        [
            'heading' => 'Laporan',
            'items' => [
                ['label'=>'Laporan Harian','icon'=>'fa-calendar-day',
                 'href'=>$href('administrasi.laporan.harian','/administrasi/laporan/harian'),
                 'active'=>request()->is('administrasi/laporan/harian*')],
            ],
        ],
    ];

    $menu = $role === 'super_admin' ? $menuSuperAdmin : $menuAdministrasi;
@endphp

{{-- Topbar --}}
<div x-data="{ sidebarOpen:false }" class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md hover:bg-slate-100 lg:hidden"
                        aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="{{ $role==='super_admin' ? url('/super-admin/dashboard') : url('/administrasi/dashboard') }}"
                   class="flex items-center gap-2">
                    @if($site?->path_logo)
                        <img src="{{ Storage::url($site->path_logo) }}" class="h-7 w-7 rounded" alt="Logo">
                    @else
                        <span
                            class="inline-grid place-items-center h-7 w-7 rounded bg-blue-600 text-white font-bold">R</span>
                    @endif
                    <span class="font-semibold text-slate-800">{{ $site->nama_singkat ?? 'RSUD GM Atambua' }}</span>
                </a>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden md:block">
                    <input type="search" placeholder="Cari…"
                           class="px-3 py-1.5 rounded-md border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="relative" x-data="{open:false}">
                    <button @click="open=!open"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-md hover:bg-slate-100">
                        <i class="fa-solid fa-user-circle text-lg text-slate-600"></i>
                        <span class="hidden sm:block text-sm">{{ $user?->nama ?? 'User' }}</span>
                        <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition
                         class="absolute right-0 mt-2 w-52 bg-white rounded-lg shadow border p-1">
                        <div class="px-3 py-2 text-xs text-slate-500 border-b">Role:
                            <span class="font-medium text-slate-700">{{ $role }}</span>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded hover:bg-slate-50 text-sm">Profil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full text-left px-3 py-2 rounded hover:bg-slate-50 text-sm">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Sidebar --}}
<aside
    class="fixed inset-y-0 left-0 z-30 w-64 border-r bg-white overflow-y-auto
           transform transition-transform duration-200 lg:translate-x-0"
    :class="{'-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen}"
>
    <div class="px-4 py-4">
        @foreach($menu as $section)
            <div class="mb-5">
                <div class="px-3 mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    {{ $section['heading'] }}
                </div>
                <div class="space-y-1">
                    @foreach($section['items'] as $item)
                        @php $isActive = $item['active'] ?? false; @endphp
                        <a href="{{ $item['href'] }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg
                                  {{ $isActive ? 'bg-blue-50 text-blue-700 font-medium border border-blue-100' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i class="fa-solid {{ $item['icon'] }} w-5 text-center"></i>
                            <span class="text-sm">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</aside>
