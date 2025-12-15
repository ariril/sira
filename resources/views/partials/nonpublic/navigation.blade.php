@php
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $role = session('active_role') ?? $user?->getActiveRoleSlug() ?? 'admin_rs';
    $site = \App\Models\SiteSetting::first();

    // Accent per role (gunakan key sesuai kolom users.role)
    $roleAccent = [
      'super_admin'       => ['grad'=>'from-sky-500 to-indigo-600','pill'=>'bg-sky-50 text-sky-700 border-sky-100'],
      'admin_rs'          => ['grad'=>'from-emerald-500 to-teal-600','pill'=>'bg-emerald-50 text-emerald-700 border-emerald-100'],
      'kepala_poliklinik' => ['grad'=>'from-fuchsia-500 to-purple-600','pill'=>'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100'],
      'kepala_unit'       => ['grad'=>'from-amber-500 to-orange-600','pill'=>'bg-amber-50 text-amber-800 border-amber-100'],
      'pegawai_medis'     => ['grad'=>'from-cyan-500 to-sky-600','pill'=>'bg-cyan-50 text-cyan-700 border-cyan-100'],
    ];
    $accent = $roleAccent[$role] ?? $roleAccent['admin_rs'];

    // Helper link: pakai route kalau ada; jika tidak, fallback ke URL path
    $href = function (string $routeName, string $fallbackPath) {
        return Route::has($routeName) ? route($routeName) : url($fallbackPath);
    };

    /* =======================
       MENU: SUPER ADMIN
       ======================= */
    $menuSuperAdmin = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge',
         'href'=>$href('super_admin.dashboard','/super-admin/dashboard'),
         'active'=>request()->routeIs('super_admin.dashboard')],
      ]],
      ['heading'=>'Data Master','items'=>[
        ['label'=>'Pengguna','icon'=>'fa-users',
         'href'=>$href('super_admin.users.index','/super-admin/users'),
         'active'=>request()->routeIs('super_admin.users.*')],
        ['label'=>'Unit','icon'=>'fa-diagram-project',
         'href'=>$href('super_admin.units.index','/super-admin/units'),
         'active'=>request()->routeIs('super_admin.units.*')],
        ['label'=>'Profesi','icon'=>'fa-user-doctor',
         'href'=>$href('super_admin.professions.index','/super-admin/professions'),
         'active'=>request()->routeIs('super_admin.professions.*')],
      ]],
      
      ['heading'=>'Konten Situs','items'=>[
        ['label'=>'Pengumuman','icon'=>'fa-bullhorn',
         'href'=>$href('super_admin.announcements.index','/super-admin/announcements'),
         'active'=>request()->routeIs('super_admin.announcements.*')],
        ['label'=>'FAQ','icon'=>'fa-circle-question',
         'href'=>$href('super_admin.faqs.index','/super-admin/faqs'),
         'active'=>request()->routeIs('super_admin.faqs.*')],
        ['label'=>'Halaman Tentang','icon'=>'fa-file-lines',
         'href'=>$href('super_admin.about-pages.index','/super-admin/about-pages'),
         'active'=>request()->routeIs('super_admin.about-pages.*')],
        ['label'=>'Pengaturan Situs','icon'=>'fa-gear',
         'href'=>$href('super_admin.site-settings.index','/super-admin/site-settings'),
         'active'=>request()->routeIs('super_admin.site-settings.*')],
      ]],
      ['heading'=>'Laporan','items'=>[
        ['label'=>'Kesehatan Sistem','icon'=>'fa-heart-pulse',
         'href'=>$href('super_admin.reports.system_health','/super-admin/reports/system-health'),
         'active'=>request()->routeIs('super_admin.reports.system_health')],
      ]],
    ];

    /* =======================
       MENU: ADMIN RS
       ======================= */
    $menuAdminRS = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge',
         'href'=>$href('admin_rs.dashboard','/admin-rs/dashboard'),
         'active'=>request()->routeIs('admin_rs.dashboard')],
      ]],
      ['heading'=>'Absensi Pegawai','items'=>[
        ['label'=>'Upload Excel Absensi','icon'=>'fa-file-arrow-up',
         'href'=>$href('admin_rs.attendances.import.form','/admin-rs/attendances/import'),
         'active'=>request()->is('admin-rs/attendances/import*')],
        ['label'=>'Rekap Absensi','icon'=>'fa-calendar-check',
      'href'=>$href('admin_rs.attendances.index','/admin-rs/attendances'),
      'active'=>request()->routeIs('admin_rs.attendances.index') || request()->routeIs('admin_rs.attendances.show')],
        ['label'=>'Batch Import','icon'=>'fa-database',
         'href'=>$href('admin_rs.attendances.batches','/admin-rs/attendances/batches'),
         'active'=>request()->is('admin-rs/attendances/batches*')],
      ]],
      ['heading'=>'Penilaian Kinerja','items'=>[
        ['label'=>'Approval (Level 1)','icon'=>'fa-list-check',
         'href'=>$href('admin_rs.assessments.pending','/admin-rs/assessments/pending'),
         'active'=>request()->is('admin-rs/assessments/pending*')],
        ['label'=>'Penilaian 360','icon'=>'fa-people-arrows',
         'href'=>$href('admin_rs.multi_rater.index','/admin-rs/multi-rater'),
         'active'=>request()->routeIs('admin_rs.multi_rater.*')],
      ]],
      ['heading'=>'Kinerja','items'=>[
        ['label'=>'Kriteria Kinerja','icon'=>'fa-list-check',
         'href'=>$href('admin_rs.performance-criterias.index','/admin-rs/performance-criterias'),
         'active'=>request()->routeIs('admin_rs.performance-criterias.*')],
        ['label'=>'Bobot per Unit','icon'=>'fa-scale-balanced',
         'href'=>$href('admin_rs.unit-criteria-weights.index','/admin-rs/unit-criteria-weights'),
         'active'=>request()->routeIs('admin_rs.unit-criteria-weights.*')],
        ['label'=>'Periode Penilaian','icon'=>'fa-calendar-days',
         'href'=>$href('admin_rs.assessment-periods.index','/admin-rs/assessment-periods'),
         'active'=>request()->routeIs('admin_rs.assessment-periods.*')],
        ['label'=>'Data Metrics','icon'=>'fa-chart-line',
         'href'=>$href('admin_rs.metrics.index','/admin-rs/metrics'),
         'active'=>request()->routeIs('admin_rs.metrics.*')],
      ]],
      ['heading'=>'Remunerasi','items'=>[
        ['label'=>'Alokasi per Unit','icon'=>'fa-diagram-project',
         'href'=>$href('admin_rs.unit-remuneration-allocations.index','/admin-rs/unit-remuneration-allocations'),
         'active'=>request()->routeIs('admin_rs.unit-remuneration-allocations.*')],
        ['label'=>'Ringkasan Remunerasi','icon'=>'fa-calculator',
         'href'=>$href('admin_rs.remunerations.calc.index','/admin-rs/remunerations/calc'),
         'active'=>request()->is('admin-rs/remunerations/calc*')],
        ['label'=>'Daftar Remunerasi','icon'=>'fa-money-bill-trend-up',
         'href'=>$href('admin_rs.remunerations.index','/admin-rs/remunerations'),
         'active'=>request()->routeIs('admin_rs.remunerations.index') || request()->routeIs('admin_rs.remunerations.show')],
      ]],
    ];

    /* =======================
       MENU: KEPALA UNIT
       ======================= */
    $menuKepalaUnit = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge',
         'href'=>$href('kepala_unit.dashboard','/kepala-unit/dashboard'),
         'active'=>request()->routeIs('kepala_unit.dashboard')],
      ]],
      ['heading'=>'Bobot Kriteria','items'=>[
        ['label'=>'Bobot per Unit','icon'=>'fa-scale-balanced',
         'href'=>$href('kepala_unit.unit-criteria-weights.index','/kepala-unit/unit-criteria-weights'),
         'active'=>request()->routeIs('kepala_unit.unit-criteria-weights.*')],
      ]],
      ['heading'=>'Tugas Tambahan','items'=>[
        ['label'=>'Daftar Tugas','icon'=>'fa-list-ul',
         'href'=>$href('kepala_unit.additional-tasks.index','/kepala-unit/additional-tasks'),
         'active'=>request()->routeIs('kepala_unit.additional-tasks.*')],
        ['label'=>'Klaim Tugas (Monitor)','icon'=>'fa-user-check',
         'href'=>$href('kepala_unit.additional_task_claims.index','/kepala-unit/additional-task-claims'),
         'active'=>request()->routeIs('kepala_unit.additional_task_claims.index')],
        ['label'=>'Review Klaim','icon'=>'fa-clipboard-check',
         'href'=>$href('kepala_unit.additional_task_claims.review_index','/kepala-unit/additional-task-claims/review'),
          'active'=>request()->routeIs('kepala_unit.additional_task_claims.review_*')],
      ]],
      ['heading'=>'Penilaian Kinerja','items'=>[
        ['label'=>'Approval (Level 2)','icon'=>'fa-list-check',
         'href'=>$href('kepala_unit.assessments.pending','/kepala-unit/assessments/pending'),
         'active'=>request()->is('kepala-unit/assessments/pending*')],
        ['label'=>'Penilaian 360','icon'=>'fa-people-arrows',
        'href'=>$href('kepala_unit.multi_rater.index','/kepala-unit/multi-rater'),
        'active'=>request()->routeIs('kepala_unit.multi_rater.*')],
      ]],
      ['heading'=>'Ulasan Pasien','items'=>[
        ['label'=>'Approval Ulasan','icon'=>'fa-star',
         'href'=>$href('kepala_unit.reviews.index','/kepala-unit/reviews'),
         'active'=>request()->routeIs('kepala_unit.reviews.*')],
      ]],
    ];

    /* =======================
       MENU: KEPALA POLIKLINIK
       ======================= */
    $menuKepalaPoli = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge',
         'href'=>$href('kepala_poliklinik.dashboard','/kepala-poliklinik/dashboard'),
         'active'=>request()->routeIs('kepala_poliklinik.dashboard')],
      ]],
      ['heading'=>'Bobot Kriteria','items'=>[
        ['label'=>'Approval Bobot','icon'=>'fa-scale-balanced',
         'href'=>$href('kepala_poliklinik.unit_criteria_weights.index','/kepala-poliklinik/unit-criteria-weights'),
         'active'=>request()->routeIs('kepala_poliklinik.unit_criteria_weights.*')],
      ]],
      ['heading'=>'Penilaian Kinerja','items'=>[
        ['label'=>'Approval Final (Lv.3)','icon'=>'fa-list-check',
         'href'=>$href('kepala_poliklinik.assessments.pending','/kepala-poliklinik/assessments/pending'),
        'active'=>request()->is('kepala-poliklinik/assessments/pending*')],
        ['label'=>'Penilaian 360','icon'=>'fa-people-arrows',
        'href'=>$href('kepala_poliklinik.multi_rater.index','/kepala-poliklinik/multi-rater'),
        'active'=>request()->routeIs('kepala_poliklinik.multi_rater.*')],
      ]],
      ['heading'=>'Remunerasi','items'=>[
        ['label'=>'Monitoring Remunerasi','icon'=>'fa-money-bill-trend-up',
         'href'=>$href('kepala_poliklinik.remunerations.index','/kepala-poliklinik/remunerations'),
         'active'=>request()->routeIs('kepala_poliklinik.remunerations.*')],
      ]],
    ];

    /* =======================
       MENU: PEGAWAI MEDIS
       ======================= */
    $menuPegawaiMedis = [
      ['heading'=>'Dashboard','items'=>[
        ['label'=>'Dashboard','icon'=>'fa-gauge',
         'href'=>$href('pegawai_medis.dashboard','/pegawai-medis/dashboard'),
         'active'=>request()->routeIs('pegawai_medis.dashboard')],
      ]],
      ['heading'=>'Kinerja','items'=>[
        ['label'=>'Penilaian Saya','icon'=>'fa-clipboard-check',
         'href'=>$href('pegawai_medis.assessments.index','/pegawai-medis/assessments'),
         'active'=>request()->routeIs('pegawai_medis.assessments.*')],
      ['label'=>'Kriteria & Bobot Aktif','icon'=>'fa-scale-balanced',
      'href'=>$href('pegawai_medis.unit_criteria_weights.index','/pegawai-medis/unit-criteria-weights'),
      'active'=>request()->routeIs('pegawai_medis.unit_criteria_weights.*')],
        ['label'=>'Kontribusi Tambahan','icon'=>'fa-hand-holding-heart',
         'href'=>$href('pegawai_medis.additional-contributions.index','/pegawai-medis/additional-contributions'),
         'active'=>request()->routeIs('pegawai_medis.additional-contributions.*')],
        ['label'=>'Penilaian 360','icon'=>'fa-people-arrows',
         'href'=>$href('pegawai_medis.multi_rater.index','/pegawai-medis/multi-rater'),
        'active'=>request()->routeIs('pegawai_medis.multi_rater.*')],
      ]],
      ['heading'=>'Remunerasi','items'=>[
        ['label'=>'Remunerasi Saya','icon'=>'fa-money-bill-trend-up',
         'href'=>$href('pegawai_medis.remunerations.index','/pegawai-medis/remunerations'),
         'active'=>request()->routeIs('pegawai_medis.remunerations.*')],
        // ['label'=>'Data Remunerasi','icon'=>'fa-database',
        //  'href'=>$href('pegawai_medis.remuneration_data.index','/pegawai-medis/remuneration-data'),
        //  'active'=>request()->routeIs('pegawai_medis.remuneration_data.*')],
      ]],
    ];

    // Pilih menu sesuai role
  $menu = match ($role) {
        'super_admin'       => $menuSuperAdmin,
        'kepala_unit'       => $menuKepalaUnit,
        'kepala_poliklinik' => $menuKepalaPoli,
    'pegawai_medis'     => $menuPegawaiMedis,
        default             => $menuAdminRS, // admin_rs
    };
@endphp

{{-- NAV WRAPPER: share Alpine state between topbar and sidebar --}}
<div x-data="{ sidebarOpen:false }">
  {{-- TOPBAR (fixed) --}}
  <div class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
      <div class="h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md hover:bg-slate-100 lg:hidden"
                        aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
    <a href="{{ match($role){
        'super_admin'       => url('/super-admin/dashboard'),
        'kepala_unit'       => url('/kepala-unit/dashboard'),
        'kepala_poliklinik' => url('/kepala-poliklinik/dashboard'),
  'pegawai_medis'     => url('/pegawai-medis/dashboard'),
        default             => url('/admin-rs/dashboard'),
    } }}" class="flex items-center gap-2">
                    <div
                        class="h-8 w-8 rounded-lg bg-gradient-to-br {{ $accent['grad'] }} grid place-items-center text-white font-semibold">
                        {{ \Illuminate\Support\Str::of($site?->short_name ?? 'RS')->substr(0,1) }}
                    </div>
                    <span class="font-semibold text-slate-800">{{ $site?->short_name ?? 'RSUD GM Atambua' }}</span>
                </a>
            </div>

      <div class="flex items-center gap-3">
                <div class="relative" x-data="{open:false}">
                    <button @click="open=!open"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-md hover:bg-slate-100">
                        <i class="fa-solid fa-user-circle text-lg text-slate-600"></i>
                        <span class="hidden sm:block text-sm">{{ $user?->name ?? 'Pengguna' }}</span>
                        <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition
                       class="absolute right-0 mt-2 w-60 bg-white rounded-lg shadow border p-1 space-y-1">
                      <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded hover:bg-slate-50 text-sm">Profil</a>
                      @if($user && $user->roles->count() > 1)
                        <div class="px-3 pt-2 border-t text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ganti Peran</div>
                        <div class="max-h-48 overflow-auto py-1">
                          @foreach($user->roles as $r)
                            @php $isActive = ($role === $r->slug); @endphp
                            <form method="POST" action="{{ route('auth.switch-role') }}" class="mb-1 last:mb-0">
                              @csrf
                              <input type="hidden" name="role" value="{{ $r->slug }}">
                              <button class="w-full flex items-center gap-2 px-3 py-1.5 rounded text-sm border
                                {{ $isActive ? 'bg-slate-100 border-slate-200 font-semibold' : 'hover:bg-slate-50 border-transparent' }}">
                                <i class="fa-solid fa-repeat text-xs"></i>
                                <span>{{ \Illuminate\Support\Str::headline(str_replace('_',' ',$r->slug)) }}</span>
                                @if($isActive)
                                  <i class="fa-solid fa-check text-green-600 ml-auto"></i>
                                @endif
                              </button>
                            </form>
                          @endforeach
                        </div>
                      @endif
                      <form method="POST" action="{{ route('logout') }}" class="border-t pt-1">
                        @csrf
                        <button class="w-full text-left px-3 py-2 rounded hover:bg-slate-50 text-sm">Keluar</button>
                      </form>
                    </div>
                </div>
            </div>
      </div>
    </div>
  </div>

  {{-- BACKDROP (mobile only) --}}
  <div x-cloak x-show="sidebarOpen" x-transition.opacity
     class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="sidebarOpen=false"></div>

  {{-- SIDEBAR (mulai di bawah topbar) --}}
  <aside
    x-cloak
    class="fixed top-14 bottom-0 left-0 z-50 w-64 border-r bg-white overflow-y-auto
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
</div>
