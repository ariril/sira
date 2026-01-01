<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\{DB, Log, View, Cache, Schema, Blade};
use App\Models\{Profession, SiteSetting, AboutPage};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\CriteriaEngine\CriteriaRegistry::class, function () {
            return new \App\Services\CriteriaEngine\CriteriaRegistry();
        });

        $this->app->singleton(\App\Services\CriteriaEngine\CriteriaAggregator::class, function ($app) {
            return new \App\Services\CriteriaEngine\CriteriaAggregator(
                $app->make(\App\Services\CriteriaEngine\CriteriaRegistry::class)
            );
        });

        $this->app->singleton(\App\Services\CriteriaEngine\CriteriaNormalizer::class, function () {
            return new \App\Services\CriteriaEngine\CriteriaNormalizer();
        });

        $this->app->bind(
            \App\Services\CriteriaEngine\Contracts\WeightProvider::class,
            \App\Services\CriteriaEngine\WeightProviders\ConfiguredWeightProvider::class
        );

        $this->app->singleton(\App\Services\CriteriaEngine\PerformanceScoreService::class, function ($app) {
            return new \App\Services\CriteriaEngine\PerformanceScoreService(
                $app->make(\App\Services\CriteriaEngine\CriteriaAggregator::class),
                $app->make(\App\Services\CriteriaEngine\CriteriaNormalizer::class),
                $app->make(\App\Services\CriteriaEngine\Contracts\WeightProvider::class),
                $app->make(\App\Services\CriteriaEngine\CriteriaRegistry::class),
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('review-invite', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        RateLimiter::for('review-submit', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        // (opsional) inisialisasi DB - dibungkus try/catch
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        Paginator::useTailwind();
        Paginator::defaultView('vendor.pagination.tailwind-no-info');

        $this->setAppTimezone();
        // Hindari query saat artisan command (migrate/seed/queue) berjalan
        if ($this->app->runningInConsole()) {
            return;
        }

        // Login modal tidak lagi memerlukan data profesi/role

        // Blade directives untuk multi-role
        Blade::if('role', function (string $slug) {
            $u = auth()->user();
            return $u && $u->hasRole($slug);
        });
        Blade::if('anyrole', function (...$slugs) {
            $u = auth()->user();
            if (!$u) return false;
            foreach ($slugs as $s) { if ($u->hasRole($s)) return true; }
            return false;
        });
        Blade::if('allroles', function (...$slugs) {
            $u = auth()->user();
            if (!$u) return false;
            foreach ($slugs as $s) { if (!$u->hasRole($s)) return false; }
            return true;
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

    private function setAppTimezone(): void
    {
        if (!Schema::hasTable('site_settings')) return;

        try {
            $tz = Cache::remember('site.timezone', 300, function () {
                return SiteSetting::query()->value('timezone');
            });

            if ($tz) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat timezone aplikasi: '.$e->getMessage());
        }
    }
}
