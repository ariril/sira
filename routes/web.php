<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;

// Public pages (English slugs)
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\FaqController;
use App\Http\Controllers\Web\AboutPageController;
use App\Http\Controllers\Web\RemunerationDataController;
use App\Http\Controllers\Web\PublicReviewController;

// Dashboards per role
use App\Http\Controllers\Web\SuperAdmin\DashboardController as SADashboard;
use App\Http\Controllers\Web\UnitHead\DashboardController as KUDashboard;          // kepala_unit
use App\Http\Controllers\Web\Administration\DashboardController as AdminDashboard; // administrasi
use App\Http\Controllers\Web\MedicalStaff\DashboardController as PMDashboard;      // pegawai_medis
use App\Http\Controllers\Web\PolyclinicHead\DashboardController as KPDashboard;    // kepala_poliklinik (baru)

Route::get('/', [HomeController::class, 'index'])->name('home');

/**
 * Public, English slugs
 */
Route::get('/announcements',            [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{slug}',     [AnnouncementController::class, 'show'])->name('announcements.show');

Route::get('/faqs',                     [FaqController::class, 'index'])->name('faqs.index');

Route::get('/about-pages/{type}',       [AboutPageController::class, 'show'])->name('about_pages.show');

Route::get('/remuneration-data',        [RemunerationDataController::class, 'index'])->name('remuneration.data');

// Public review form (English slug)
Route::get('/reviews',                  [PublicReviewController::class, 'create'])->name('reviews.create');
Route::post('/reviews',                 [PublicReviewController::class, 'store'])->name('reviews.store');

/**
 * Authenticated dashboard redirect
 */
Route::middleware(['auth','verified'])
    ->get('/dashboard', [HomeController::class, 'index'])
    ->name('dashboard');

/**
 * Role dashboards (prefix & route names dibiarkan seperti semula agar UI lama tetap jalan)
 */
Route::middleware(['auth','verified'])->group(function () {
    Route::prefix('super-admin')->name('super_admin.')->middleware('role:super_admin')->group(function () {
        Route::get('/dashboard', [SADashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('kepala-unit')->name('kepala_unit.')->middleware('role:kepala_unit')->group(function () {
        Route::get('/dashboard', [KUDashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('administrasi')->name('administrasi.')->middleware('role:administrasi')->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    });

    // NEW: Kepala Poliklinik
    Route::prefix('kepala-poliklinik')->name('kepala_poliklinik.')->middleware('role:kepala_poliklinik')->group(function () {
        Route::get('/dashboard', [KPDashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('pegawai-medis')->name('pegawai_medis.')->middleware('role:pegawai_medis')->group(function () {
        Route::get('/dashboard', [PMDashboard::class, 'index'])->name('dashboard');
    });
});

/**
 * Profile
 */
Route::middleware('auth')->group(function () {
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
