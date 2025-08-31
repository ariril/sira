<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\UlasanPublicController;

use App\Http\Controllers\Web\SuperAdmin\DashboardController as SADashboard;
use App\Http\Controllers\Web\KepalaUnit\DashboardController as KUDashboard;
use App\Http\Controllers\Web\Administrasi\DashboardController as AdminDashboard;
use App\Http\Controllers\Web\PegawaiMedis\DashboardController as PMDashboard;

Route::get('/', function () {
    return view('welcome');
})->name('home');;
Route::view('/data-remunerasi', 'pages.data-remunerasi')->name('data');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/ulasan',  [UlasanPublicController::class, 'create'])->name('ulasan.create');
Route::post('/ulasan', [UlasanPublicController::class, 'store'])->name('ulasan.store');

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
    Route::prefix('pegawai-medis')->name('pegawai_medis.')->middleware('role:pegawai_medis')->group(function () {
        Route::get('/dashboard', [PMDashboard::class, 'index'])->name('dashboard');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
