<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, View, Cache};
use App\Models\{Profession, SiteSetting, AboutPage};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Optional: buat DB jika belum ada
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        // ====== Composer untuk modal login: kirim daftar profesi ======
        View::composer('partials.login-modal', function ($view) {
            $view->with(
            // tetap pakai key 'profesis' agar Blade lama tidak rusak
                'profesis',
                Profession::query()
                    ->select('id','name')       // was: id, nama
                    ->orderBy('name')           // was: nama
                    ->get()
            );
        });

        // ====== Data site & menu profil (About) ======
        $site = Cache::remember('site.profile', 300, function () {
            // was: PengaturanSitus::first()
            return SiteSetting::query()->first(); // fields: name, short_name, address, phone, email, logo_path, favicon_path, footer_text, dll.
        });

        $profilPages = Cache::remember('site.profil_pages', 300, function () {
            // was: HalamanTentang with columns tipe, judul, path_gambar, diterbitkan_pada
            $rows = AboutPage::query()
                ->whereIn('type', ['tugas_fungsi','struktur','visi','misi','profil_rs'])
                ->orderBy('type')
                ->get(['type','title','image_path','published_at']);

            $labelMap = [
                'tugas_fungsi' => 'Tugas & Fungsi',
                'struktur'     => 'Struktur Organisasi',
                'visi'         => 'Visi',
                'misi'         => 'Misi',
                'profil_rs'    => 'Profil',
            ];

            // Struktur keluaran tetap sama agar Blade lama aman
            return $rows->map(fn($r) => [
                'tipe'   => $r->type,
                'label'  => $labelMap[$r->type] ?? ucfirst(str_replace('_',' ',$r->type)),
                'exists' => true,
            ])->values();
        });

        View::composer(['layouts.public','partials.*'], function ($view) use ($site, $profilPages) {
            $view->with('site', $site);
            $view->with('profilPages', $profilPages);
        });
    }
}
