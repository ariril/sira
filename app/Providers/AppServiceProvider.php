<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, View, Cache, Schema};
use App\Models\{Profession, SiteSetting, AboutPage};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // (opsional) inisialisasi DB
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        /**
         * Jangan jalankan view composer & query model saat running di console
         * (migrate/seed/queue) agar tidak error sebelum tabel ada.
         */
        if ($this->app->runningInConsole()) {
            return;
        }

        // ====== Composer untuk modal login: kirim daftar profesi ======
        View::composer('partials.login-modal', function ($view) {
            $profesis = collect(); // default kosong

            if (Schema::hasTable('professions')) {
                $profesis = Profession::query()
                    ->select('id', 'name')    // kolom baru
                    ->orderBy('name')
                    ->get();
            }

            // Tetap pakai key 'profesis' agar Blade lama tidak rusak
            $view->with('profesis', $profesis);
        });

        // ====== Data site & menu profil (About) ======
        View::composer(['layouts.public', 'partials.*'], function ($view) {
            $site = null;
            $profilPages = collect();

            if (Schema::hasTable('site_settings')) {
                $site = Cache::remember('site.profile', 300, function () {
                    return SiteSetting::query()->first(); // name, short_name, address, phone, email, logo_path, favicon_path, footer_text, ...
                });
            }

            if (Schema::hasTable('about_pages')) {
                $profilPages = Cache::remember('site.profil_pages', 300, function () {
                    $rows = AboutPage::query()
                        ->whereIn('type', ['tugas_fungsi', 'struktur', 'visi', 'misi', 'profil_rs'])
                        ->orderBy('type')
                        ->get(['type', 'title', 'image_path', 'published_at']);

                    $labelMap = [
                        'tugas_fungsi' => 'Tugas & Fungsi',
                        'struktur'     => 'Struktur Organisasi',
                        'visi'         => 'Visi',
                        'misi'         => 'Misi',
                        'profil_rs'    => 'Profil',
                    ];

                    // Keluarkan struktur yang kompatibel dengan Blade lama
                    return $rows->map(fn ($r) => [
                        'tipe'   => $r->type,
                        'label'  => $labelMap[$r->type] ?? ucfirst(str_replace('_', ' ', $r->type)),
                        'exists' => true,
                    ])->values();
                });
            }

            $view->with('site', $site);
            $view->with('profilPages', $profilPages);
        });
    }
}
