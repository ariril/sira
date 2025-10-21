<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log, View, Cache, Schema};
use App\Models\{Profession, SiteSetting, AboutPage};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // (opsional) inisialisasi DB - dibungkus try/catch
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        Paginator::useTailwind();
        Paginator::defaultView('vendor.pagination.tailwind-no-info');
        // Hindari query saat artisan command (migrate/seed/queue) berjalan
        if ($this->app->runningInConsole()) {
            return;
        }

        /**
         * ===== Modal Login: daftar profesi =====
         * Blade lama mengakses $p->nama, jadi kita aliases name AS nama
         */
        View::composer('partials.login-modal', function ($view) {
            $profesis = collect();

            if (Schema::hasTable('professions')) {
                $profesis = Profession::query()
                    ->selectRaw('id, name as nama')
                    ->orderBy('name')
                    ->get();
            }

            $view->with('profesis', $profesis);
        });

        /**
         * ===== Site profile & menu Profil (About) =====
         * - $site disediakan ke layout/partials
         * - $profilPages: selalu keluarkan 5 item default dgn flag 'exists'
         * - Jika kolom type dicast enum, gunakan ->value agar jadi string
         */
        View::composer(['layouts.public', 'partials.*'], function ($view) {
            $site = null;
            $profilPages = collect();

            if (Schema::hasTable('site_settings')) {
                $site = Cache::remember('site.profile', 300, function () {
                    return SiteSetting::query()->first();
                });
            }

            if (Schema::hasTable('about_pages')) {
                $profilPages = Cache::remember('site.profil_pages', 300, function () {
                    $wanted = ['profil_rs', 'visi', 'misi', 'struktur', 'tugas_fungsi'];

                    // Ambil yang aktif, cukup kolom minimal
                    $rows = AboutPage::query()
                        ->whereIn('type', $wanted)
                        ->where('is_active', 1)
                        ->orderBy('type')
                        ->get(['type', 'title', 'image_path', 'published_at']);

                    $labelMap = [
                        'profil_rs'    => 'Profil',
                        'visi'         => 'Visi',
                        'misi'         => 'Misi',
                        'struktur'     => 'Struktur Organisasi',
                        'tugas_fungsi' => 'Tugas & Fungsi',
                    ];

                    // Normalisasi hasil query (enum->string)
                    $have = $rows->map(function ($r) {
                        $type = $r->type instanceof \BackedEnum ? $r->type->value : (string) $r->type;
                        return [
                            'tipe'   => $type,
                            'exists' => true,
                        ];
                    });

                    // Susun 5 menu tetap, tandai exists=false bila belum ada kontennya
                    return collect($wanted)->map(function ($t) use ($have, $labelMap) {
                        $exists = (bool) $have->firstWhere('tipe', $t);
                        return [
                            'tipe'   => $t,
                            'label'  => $labelMap[$t] ?? ucfirst(str_replace('_', ' ', $t)),
                            'exists' => $exists,
                        ];
                    });
                });
            }

            $view->with('site', $site);
            $view->with('profilPages', $profilPages);
        });
    }
}
