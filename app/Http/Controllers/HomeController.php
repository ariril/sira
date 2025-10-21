<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\View;

use App\Models\SiteSetting;             // site_settings
use App\Models\AssessmentPeriod;        // assessment_periods
use App\Models\Announcement;            // announcements
use App\Models\Faq;                     // faqs
use App\Models\AdditionalContribution;  // additional_contributions
use App\Models\PerformanceAssessment;   // performance_assessments
use App\Models\User;                    // users

use Carbon\Carbon;

class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            $role = Auth::user()->role;

            return match ($role) {
                'super_admin'        => redirect()->route('super_admin.dashboard'),
                'kepala_unit'        => redirect()->route('kepala_unit.dashboard'),
                'kepala_poliklinik'  => redirect()->route('polyclinic_head.dashboard'),
                'admin_rs'       => redirect()->route('admin_rs.dashboard'),
                default              => redirect()->route('medical_staff.dashboard'),
            };
        }

        // ========== PUBLIC LANDING (GUEST) ==========
        Carbon::setLocale('id');

        // 1) Site/Profile
        $site = Cache::remember('site.profile', 300, fn () => SiteSetting::query()->first());

        // 2) Periode aktif
        $periodeAktif = Cache::remember('periode.aktif', 300, function () {
            return AssessmentPeriod::query()
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();
        });

        // 3) Quick stats
        $totalPegawai = Cache::remember('stat.total_pegawai', 300, fn () => User::count());

        // 3a) Rata-rata capaian (avg performance_assessments.total_wsm_score pada periode aktif)
        $capaianKinerja = Cache::remember('stat.capaian_kinerja', 300, function () use ($periodeAktif) {
            if (!$periodeAktif) return null;

            return PerformanceAssessment::query()
                ->where('assessment_period_id', $periodeAktif->id)
                ->avg('total_wsm_score'); // decimal|null
        });

        // 3b) Pengganti "Logbook Terisi": jumlah additional_contributions pada periode aktif
        $logbookTerisi = Cache::remember('stat.logbook_terisi', 300, function () use ($periodeAktif) {
            $q = AdditionalContribution::query();
            if ($periodeAktif) $q->where('assessment_period_id', $periodeAktif->id);
            return $q->count();
        });

        // 3c) Pengganti "Jadwal Dokter Besok": jumlah performance_assessments dengan assessment_date = besok
        $jadwalDokterBesok = Cache::remember('stat.jadwal_dokter_besok', 300, function () {
            $besok = Carbon::tomorrow()->toDateString();
            return PerformanceAssessment::query()
                ->whereDate('assessment_date', $besok)
                ->count();
        });

        $stats = [
            [
                'icon'  => 'fa-users',
                'value' => number_format($totalPegawai, 0, ',', '.'),
                'label' => 'Total Pegawai',
            ],
            [
                'icon'  => 'fa-chart-line',
                'value' => $capaianKinerja !== null
                    ? number_format((float)$capaianKinerja, 1, ',', '.')
                    : '—',
                'label' => 'Capaian Kinerja',
            ],
            [
                'icon'  => 'fa-calendar-check',
                'value' => $periodeAktif
                    ? ($this->formatBulanTahun($periodeAktif->start_date) . ' – ' . $this->formatBulanTahun($periodeAktif->end_date))
                    : '—',
                'label' => 'Periode Aktif',
            ],
            [
                'icon'  => 'fa-file-lines',
                'value' => number_format($logbookTerisi, 0, ',', '.'),
                'label' => 'Logbook Terisi', // label UI dipertahankan
            ],
        ];

        // 4) Announcements terbaru (published_at / expired_at)
        $announcements = Cache::remember('pengumuman.terbaru', 300, function () {
            return Announcement::query()
                ->where(function ($q) {
                    $q->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->orderByDesc('published_at')
                ->limit(6)
                ->get();
        });

        // 5) FAQ aktif
        $faqs = Cache::remember('faq.aktif', 300, function () {
            return Faq::query()
                ->where('is_active', 1)
                ->orderBy('order')
                ->limit(10)
                ->get(['id','question','answer','category']);
        });

        // 6) Quick links (UI sama; rute disesuaikan)
        $links = [
            ['icon' => 'fa-table',          'title' => 'Data Remuneration', 'desc' => 'Lihat data remunerasi pegawai', 'href' => route('remuneration.data')],
            ['icon' => 'fa-clipboard-list', 'title' => 'Logbook Harian',    'desc' => 'Isi logbook kerja harian',      'href' => route('login') . '?redirect=logbook'],
            ['icon' => 'fa-chart-bar',      'title' => 'Laporan SKP',       'desc' => 'Lihat laporan kinerja SKP',     'href' => '#'],
            ['icon' => 'fa-download',       'title' => 'Unduh Formulir',    'desc' => 'Download formulir terbaru',     'href' => '#'],
        ];

        return view('welcome', compact(
            'site',
            'periodeAktif',
            'stats',
            'announcements',
            'faqs',
            'links',
            'jadwalDokterBesok'
        ));
    }

    private function formatBulanTahun($date): string
    {
        return $date ? Carbon::parse($date)->translatedFormat('F Y') : '—';
    }
}
