<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Models\Profesi;

use App\Models\PengaturanSitus;
use App\Models\HalamanTentang;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            DB::statement('CREATE DATABASE IF NOT EXISTS sira');
        } catch (\Throwable $e) {
            Log::error('Gagal membuat database: '.$e->getMessage());
        }

        View::composer('partials.login-modal', function ($view) {
            $view->with(
                'profesis',
                Profesi::select('id','nama')->orderBy('nama')->get()
            );
        });

        /**
         * View composer baru: inject data situs & menu profil
         * ke layouts.public dan semua partials.*
         *
         * - $site: dari tabel pengaturan_situs (nama, singkatan, alamat, telepon, email, logo, sosmed, footer, dll.)
         * - $profilPages: daftar item profil dari tabel halaman_tentang (tipe: profil_rs, tugas_fungsi, struktur, visi, misi)
         */
        $site = Cache::remember('site.profile', 300, function () {
            return PengaturanSitus::query()->first();
        });

        $profilPages = Cache::remember('site.profil_pages', 300, function () {
            $rows = HalamanTentang::query()
                ->whereIn('tipe', ['tugas_fungsi','struktur','visi','misi','profil_rs'])
                ->orderBy('tipe')
                ->get(['tipe','judul','path_gambar','diterbitkan_pada']);

            $labelMap = [
                'tugas_fungsi' => 'Tugas & Fungsi',
                'struktur'     => 'Struktur Organisasi',
                'visi'         => 'Visi',
                'misi'         => 'Misi',
                'profil_rs'    => 'Profil',
            ];

            return $rows->map(fn($r) => [
                'tipe'   => $r->tipe,
                'label'  => $labelMap[$r->tipe] ?? ucfirst(str_replace('_',' ',$r->tipe)),
                'exists' => true,
            ])->values();
        });

        View::composer(['layouts.public','partials.*'], function ($view) use ($site, $profilPages) {
            $view->with('site', $site);
            $view->with('profilPages', $profilPages);
        });
    }
}
