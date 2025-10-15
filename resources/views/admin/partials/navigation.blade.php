@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $role = $user?->role ?? 'administrasi';
    $site = \App\Models\SiteSetting::first();

    // Accent per role (pakai nama role di DB)
    $roleAccent = [
      'super_admin'        => ['grad'=>'from-sky-500 to-indigo-600','pill'=>'bg-sky-50 text-sky-700 border-sky-100'],
      'administrasi'       => ['grad'=>'from-emerald-500 to-teal-600','pill'=>'bg-emerald-50 text-emerald-700 border-emerald-100'],
      'kepala_poliklinik'  => ['grad'=>'from-fuchsia-500 to-purple-600','pill'=>'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100'],
      'kepala_unit'        => ['grad'=>'from-amber-500 to-orange-600','pill'=>'bg-amber-50 text-amber-800 border-amber-100'],
      'pegawai_medis'      => ['grad'=>'from-blue-500 to-indigo-600','pill'=>'bg-blue-50 text-blue-700 border-blue-100'],
    ];
    $accent = $roleAccent[$role] ?? $roleAccent['administrasi'];

    $href = function (string $routeName, string $fallbackPath) {
        return Route::has($routeName) ? route($routeName) : url($fallbackPath);
    };

    /* --------- MENU --------- */
    $menuSuperAdmin = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge','href'=>$href('super_admin.dashboard','/super-admin/dashboard'),'active'=>request()->routeIs('super_admin.dashboard')],
      ]],
      ['heading'=>'Master Data','items'=>[
        ['label'=>'User','icon'=>'fa-users','href'=>$href('super_admin.users.index','/super-admin/users'),'active'=>request()->routeIs('super_admin.users.*')],
        ['label'=>'Unit Kerja','icon'=>'fa-diagram-project','href'=>$href('super_admin.unit_kerja.index','/super-admin/unit-kerja'),'active'=>request()->routeIs('super_admin.unit_kerja.*')],
        ['label'=>'Profession','icon'=>'fa-user-doctor','href'=>$href('super_admin.profesi.index','/super-admin/profesi'),'active'=>request()->routeIs('super_admin.profesi.*')],
      ]],
      ['heading'=>'Kinerja','items'=>[
        ['label'=>'Kriteria Kinerja','icon'=>'fa-list-check','href'=>$href('super_admin.kriteria.index','/super-admin/kriteria-kinerja'),'active'=>request()->routeIs('super_admin.kriteria.*')],
        ['label'=>'Bobot per Unit','icon'=>'fa-scale-balanced','href'=>$href('super_admin.bobot.index','/super-admin/bobot-kriteria-unit'),'active'=>request()->routeIs('super_admin.bobot.*')],
        ['label'=>'Periode Penilaian','icon'=>'fa-calendar-days','href'=>$href('super_admin.periode.index','/super-admin/periode-penilaian'),'active'=>request()->routeIs('super_admin.periode.*')],
        ['label'=>'Monitoring Penilaian','icon'=>'fa-eye','href'=>$href('super_admin.monitoring.penilaian','/super-admin/monitoring/penilaian'),'active'=>request()->is('super-admin/monitoring/penilaian*')],
      ]],
      ['heading'=>'Attendance','items'=>[
        ['label'=>'Impor Excel','icon'=>'fa-file-arrow-up','href'=>$href('super_admin.kehadiran.import','/super-admin/kehadiran/import'),'active'=>request()->is('super-admin/kehadiran/import*')],
        ['label'=>'Rekap Attendance','icon'=>'fa-calendar-check','href'=>$href('super_admin.kehadiran.index','/super-admin/kehadiran'),'active'=>request()->is('super-admin/kehadiran*')],
      ]],
      ['heading'=>'Remuneration','items'=>[
        ['label'=>'Perhitungan & Publikasi','icon'=>'fa-money-bill-trend-up','href'=>$href('super_admin.remunerasi.index','/super-admin/remunerasi'),'active'=>request()->routeIs('super_admin.remunerasi.*')],
        ['label'=>'Laporan Remuneration','icon'=>'fa-file-invoice','href'=>$href('super_admin.remunerasi.report','/super-admin/remunerasi/laporan'),'active'=>request()->is('super-admin/remunerasi/laporan*')],
      ]],
      ['heading'=>'Feedback Pasien','items'=>[
        ['label'=>'Review Pasien','icon'=>'fa-comments','href'=>$href('super_admin.ulasan.index','/super-admin/ulasan'),'active'=>request()->routeIs('super_admin.ulasan.*')],
      ]],
      ['heading'=>'Konten Situs','items'=>[
        ['label'=>'Announcement','icon'=>'fa-bullhorn','href'=>$href('super_admin.pengumuman.index','/super-admin/konten/pengumuman'),'active'=>request()->routeIs('super_admin.pengumuman.*')],
        ['label'=>'FAQ','icon'=>'fa-circle-question','href'=>$href('super_admin.faq.index','/super-admin/konten/faq'),'active'=>request()->routeIs('super_admin.faq.*')],
        ['label'=>'Halaman Tentang','icon'=>'fa-file-lines','href'=>$href('super_admin.tentang.index','/super-admin/konten/tentang'),'active'=>request()->routeIs('super_admin.tentang.*')],
        ['label'=>'Pengaturan Situs','icon'=>'fa-gear','href'=>$href('super_admin.pengaturan.index','/super-admin/pengaturan-situs'),'active'=>request()->routeIs('super_admin.pengaturan.*')],
      ]],
      ['heading'=>'Laporan & Ekspor','items'=>[
        ['label'=>'Semua Laporan','icon'=>'fa-table','href'=>$href('super_admin.laporan.index','/super-admin/laporan'),'active'=>request()->routeIs('super_admin.laporan.*')],
      ]],
    ];

    $menuAdministrasi = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge','href'=>$href('administrasi.dashboard','/administrasi/dashboard'),'active'=>request()->routeIs('administrasi.dashboard')],
      ]],
      ['heading'=>'Absensi Pegawai','items'=>[
        ['label'=>'Upload Excel Absensi','icon'=>'fa-file-arrow-up','href'=>$href('administrasi.attendance.import','/administrasi/attendance/import'),'active'=>request()->is('administrasi/attendance/import*')],
        ['label'=>'Rekap Absensi','icon'=>'fa-calendar-check','href'=>$href('administrasi.attendance.index','/administrasi/attendance'),'active'=>request()->is('administrasi/attendance*')],
      ]],
      ['heading'=>'Penilaian Kinerja','items'=>[
        ['label'=>'Multi-Level Approval','icon'=>'fa-list-check','href'=>$href('administrasi.approvals.index','/administrasi/assessment-approvals'),'active'=>request()->is('administrasi/assessment-approvals*')],
      ]],
      ['heading'=>'Remunerasi','items'=>[
        ['label'=>'Alokasi per Unit','icon'=>'fa-diagram-project','href'=>$href('administrasi.allocations.index','/administrasi/unit-remuneration-allocations'),'active'=>request()->is('administrasi/unit-remuneration-allocations*')],
        ['label'=>'Publikasi Hasil','icon'=>'fa-bullhorn','href'=>$href('administrasi.remunerations.publish','/administrasi/remunerations/publish'),'active'=>request()->is('administrasi/remunerations/publish*')],
      ]],
    ];

    $menu = $role === 'super_admin' ? $menuSuperAdmin : $menuAdministrasi;
@endphp

{{-- TOPBAR (fixed) --}}
<div x-data="{ sidebarOpen:false }" class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md hover:bg-slate-100 lg:hidden" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="{{ $role==='super_admin' ? url('/super-admin/dashboard') : url('/administrasi/dashboard') }}" class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br {{ $accent['grad'] }} grid place-items-center text-white font-semibold">
                        {{ \Illuminate\Support\Str::of($site?->short_name ?? 'RS')->substr(0,1) }}
                    </div>
                    <span class="font-semibold text-slate-800">{{ $site?->short_name ?? 'RSUD GM Atambua' }}</span>
                </a>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                    <i class="fa-solid fa-user-shield"></i> {{ \Illuminate\Support\Str::headline(str_replace('_',' ',$role)) }}
                </span>
                <div class="relative" x-data="{open:false}">
                    <button @click="open=!open" class="flex items-center gap-2 px-3 py-1.5 rounded-md hover:bg-slate-100">
                        <i class="fa-solid fa-user-circle text-lg text-slate-600"></i>
                        <span class="hidden sm:block text-sm">{{ $user?->name ?? 'User' }}</span>
                        <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition class="absolute right-0 mt-2 w-52 bg-white rounded-lg shadow border p-1">
                        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded hover:bg-slate-50 text-sm">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full text-left px-3 py-2 rounded hover:bg-slate-50 text-sm">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- SIDEBAR (mulai di bawah topbar) --}}
<aside
    class="fixed top-14 bottom-0 left-0 z-40 w-64 border-r bg-white overflow-y-auto
           transform transition-transform duration-200 lg:translate-x-0"
    :class="{'-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen}">
    {{-- brand --}}
    <div class="px-4 py-5 bg-gradient-to-r {{ $accent['grad'] }} text-white">
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-white/10 grid place-items-center">
                <i class="fa-solid fa-hospital"></i>
            </div>
            <div>
                <p class="text-sm/5 opacity-80">{{ $site?->short_name ?? 'RSUD GM Atambua' }}</p>
                <p class="text-base font-semibold">{{ \Illuminate\Support\Str::headline(str_replace('_',' ',$role)) }}</p>
            </div>
        </div>
    </div>

    {{-- menu --}}
    <div class="px-4 py-4">
        @foreach($menu as $section)
            <div class="mb-5">
                <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    {{ $section['heading'] }}
                </div>
                <div class="space-y-1">
                    @foreach($section['items'] as $item)
                        @php $isActive = $item['active'] ?? false; @endphp
                        <a href="{{ $item['href'] }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-xl border
                                  {{ $isActive ? $accent['pill'] : 'text-slate-600 border-transparent hover:bg-slate-50' }}">
                            <i class="fa-solid {{ $item['icon'] }} w-5 text-center"></i>
                            <span class="text-sm">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</aside>
