<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;

// Public pages (English slugs)
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\FaqController;
use App\Http\Controllers\Web\AboutPageController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\RemunerationDataController;
use App\Http\Controllers\Web\PublicReviewController;

// Dashboards per role
use App\Http\Controllers\Web\SuperAdmin\DashboardController as SADashboard;
use App\Http\Controllers\Web\UnitHead\DashboardController as KUDashboard;             // kepala_unit
use App\Http\Controllers\Web\AdminHospital\DashboardController as AdminDashboard;             // admin_rs (baru)
use App\Http\Controllers\Web\MedicalStaff\DashboardController as PMDashboard;         // pegawai_medis
use App\Http\Controllers\Web\PolyclinicHead\DashboardController as KPDashboard;       // kepala_poliklinik

Route::get('/', [HomeController::class, 'index'])->name('home');

/**
 * Public, English slugs
 */
Route::get('/announcements',        [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{slug}', [AnnouncementController::class, 'show'])->name('announcements.show');

Route::get('/faqs',                 [FaqController::class, 'index'])->name('faqs.index');

Route::get('/about-pages/{type}',   [AboutPageController::class, 'show'])->name('about_pages.show');
Route::get('/contact',              [ContactController::class, 'index'])->name('contact');

// Dipindahkan ke area pegawai_medis (tidak publik lagi)
// Route::get('/remuneration-data',    [RemunerationDataController::class, 'index'])->name('remuneration.data');

// Public review form (English slug)
Route::get('/reviews',              [PublicReviewController::class, 'create'])->name('reviews.create');
Route::post('/reviews',             [PublicReviewController::class, 'store'])->name('reviews.store');

/**
 * Authenticated dashboard redirect
 */
Route::middleware(['auth','verified'])
    ->get('/dashboard', [HomeController::class, 'index'])
    ->name('dashboard');

/**
 * Role dashboards (prefix & route names dipertahankan, dengan perubahan admin_rs -> admin_rs)
 */
Route::middleware(['auth','verified'])->group(function () {
    Route::prefix('super-admin')->name('super_admin.')->middleware('role:super_admin')->group(function () {
        Route::get('/dashboard', [SADashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('kepala-unit')->name('kepala_unit.')->middleware('role:kepala_unit')->group(function () {
        Route::get('/dashboard', [KUDashboard::class, 'index'])->name('dashboard');
    });

    // GANTI: admin_rs -> admin_rs
    Route::prefix('admin-rs')->name('admin_rs.')->middleware('role:admin_rs')->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('kepala-poliklinik')->name('kepala_poliklinik.')->middleware('role:kepala_poliklinik')->group(function () {
        Route::get('/dashboard', [KPDashboard::class, 'index'])->name('dashboard');
    });

    Route::prefix('pegawai-medis')->name('pegawai_medis.')->middleware('role:pegawai_medis')->group(function () {
        Route::get('/dashboard', [PMDashboard::class, 'index'])->name('dashboard');
    });
});

/**
 * =========================
 * SUPER ADMIN AREA
 * =========================
 * Mengelola seluruh master data & konfigurasi global:
 * - units, professions, users
 * - performance_criterias (bobot/tipe)
 * - assessment_periods (buat/aktifkan/kunci)
 * - unit_criteria_weights (set global/draft push)
 * - site_settings, about_pages, faqs, announcements (manajemen konten)
 */
Route::middleware(['auth','verified','role:super_admin'])
    ->prefix('super-admin')->name('super_admin.')->group(function () {

        // Master Data
        Route::resource('units',        \App\Http\Controllers\Web\SuperAdmin\UnitController::class);
        Route::resource('professions',  \App\Http\Controllers\Web\SuperAdmin\ProfessionController::class);
        Route::resource('users',        \App\Http\Controllers\Web\SuperAdmin\UserController::class);

        // (Kinerja dipindahkan ke Admin RS)

    // Konten & pengaturan situs
    // Gunakan GET+PUT tanpa parameter agar form bisa submit ke /super-admin/site-settings langsung
    Route::get('site-settings', [\App\Http\Controllers\Web\SuperAdmin\SiteSettingController::class, 'index'])->name('site-settings.index');
    Route::put('site-settings', [\App\Http\Controllers\Web\SuperAdmin\SiteSettingController::class, 'update'])->name('site-settings.update');
        Route::resource('about-pages',   \App\Http\Controllers\Web\SuperAdmin\AboutPageManageController::class)->parameters(['about-pages' => 'aboutPage']);
        Route::resource('faqs',          \App\Http\Controllers\Web\SuperAdmin\FaqManageController::class);
        Route::resource('announcements', \App\Http\Controllers\Web\SuperAdmin\AnnouncementManageController::class);

        // Laporan ringkas
        Route::get('reports/system-health', [\App\Http\Controllers\Web\SuperAdmin\ReportController::class, 'systemHealth'])->name('reports.system_health');
    });

/**
 * =========================
 * ADMIN RS AREA (ex-admin_rs)
 * =========================
 * Tugas:
 * - Import & kelola kehadiran (attendance_import_batches, attendances)
 * - Review awal penilaian (assessment_approvals level 1)
 * - Input & kelola alokasi unit (unit_remuneration_allocations)
 * - Proses & publikasi remunerations
 * - (Opsional) kelola pengumuman/FAQ sehari-hari
 */
Route::middleware(['auth','verified','role:admin_rs'])
    ->prefix('admin-rs')->name('admin_rs.')->group(function () {

        // Import Kehadiran
        Route::get('attendances/import', [\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'create'])->name('attendances.import.form');
        Route::post('attendances/import',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'store'])->name('attendances.import.store');
        Route::get('attendances/batches',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'index'])->name('attendances.batches');
        Route::get('attendances/batches/{batch}',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'show'])->name('attendances.batches.show');
        Route::resource('attendances',   \App\Http\Controllers\Web\AdminHospital\AttendanceController::class)->only(['index','show','update','destroy']);

        // Multi-Level Assessment Approvals – Level 1 (Admin RS)
        Route::get('assessments/pending',             [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::post('assessments/{assessment}/approve',[\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject', [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');
        Route::post('assessments/{assessment}/approve',[\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject', [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');

        // Alokasi Unit untuk remunerasi
        Route::resource('unit-remuneration-allocations', \App\Http\Controllers\Web\AdminHospital\UnitRemunerationAllocationController::class)
            ->parameters(['unit-remuneration-allocations' => 'allocation']);

        // Proses & Publikasi Remunerasi
        Route::get('remunerations/calc',        [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'calcIndex'])->name('remunerations.calc.index');
        Route::post('remunerations/calc/run',   [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'runCalculation'])->name('remunerations.calc.run');
        Route::post('remunerations/{remuneration}/publish', [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'publish'])->name('remunerations.publish');
        Route::resource('remunerations', \App\Http\Controllers\Web\AdminHospital\RemunerationController::class)->only(['index','show','update']);

        // Kinerja (dipindahkan dari Super Admin)
        Route::resource('performance-criterias', \App\Http\Controllers\Web\AdminHospital\PerformanceCriteriaController::class);

    // Approval usulan kriteria baru
    Route::get('criteria-proposals', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'index'])->name('criteria_proposals.index');
    Route::post('criteria-proposals/{proposal}/approve', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'approve'])->name('criteria_proposals.approve');
    Route::post('criteria-proposals/{proposal}/reject', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'reject'])->name('criteria_proposals.reject');

        // Periode Penilaian (assessment_periods)
        Route::resource('assessment-periods', \App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class)
            ->parameters(['assessment-periods' => 'period']);
        Route::post('assessment-periods/{period}/activate', [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'activate'])->name('assessment_periods.activate');
        Route::post('assessment-periods/{period}/lock',     [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'lock'])->name('assessment_periods.lock');
        Route::post('assessment-periods/{period}/close',    [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'close'])->name('assessment_periods.close');

        // Bobot Kriteria Unit (setup awal/push draft per unit)
        Route::resource('unit-criteria-weights', \App\Http\Controllers\Web\AdminHospital\UnitCriteriaWeightController::class)
            ->only(['index','store','update','destroy','show']);
        Route::post('unit-criteria-weights/publish-draft', [\App\Http\Controllers\Web\AdminHospital\UnitCriteriaWeightController::class, 'publishDraft'])
            ->name('unit_criteria_weights.publish_draft');

        // Konten operasional (opsional)
        Route::resource('announcements', \App\Http\Controllers\Web\AdminHospital\AnnouncementDailyController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('faqs',          \App\Http\Controllers\Web\AdminHospital\FaqDailyController::class)->only(['index','create','store','edit','update','destroy']);
    });

/**
 * =========================
 * KEPALA UNIT AREA
 * =========================
 * Tugas:
 * - Menyesuaikan bobot kriteria per unit (unit_criteria_weights) → submit ke approval
 * - Membuat tugas tambahan (additional_tasks) untuk unit
 * - Monitoring klaim tugas (additional_task_claims)
 * - Review & validasi penilaian level 2 (assessment_approvals)
 */
Route::middleware(['auth','verified','role:kepala_unit'])
    ->prefix('kepala-unit')->name('kepala_unit.')->group(function () {

        // Bobot kriteria per unit
        Route::resource('unit-criteria-weights', \App\Http\Controllers\Web\UnitHead\UnitCriteriaWeightController::class)
            ->only(['index','create','store','edit','update','destroy','show']);
        Route::post('unit-criteria-weights/{weight}/submit', [\App\Http\Controllers\Web\UnitHead\UnitCriteriaWeightController::class, 'submitForApproval'])
            ->name('unit_criteria_weights.submit');
        Route::post('unit-criteria-weights/submit-all', [\App\Http\Controllers\Web\UnitHead\UnitCriteriaWeightController::class, 'submitAll'])
            ->name('unit_criteria_weights.submit_all');

        // Usulan kriteria baru
        Route::get('criteria-proposals', [\App\Http\Controllers\Web\UnitHead\CriteriaProposalController::class,'index'])->name('criteria_proposals.index');
        Route::post('criteria-proposals', [\App\Http\Controllers\Web\UnitHead\CriteriaProposalController::class,'store'])->name('criteria_proposals.store');

        // Tugas Tambahan untuk unit
        Route::resource('additional-tasks', \App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class);
        Route::patch('additional-tasks/{task}/open',     [\App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class,'open'])->name('additional_tasks.open');
        Route::patch('additional-tasks/{task}/close',    [\App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class,'close'])->name('additional_tasks.close');
        Route::patch('additional-tasks/{task}/cancel',   [\App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class,'cancel'])->name('additional_tasks.cancel');

        // Monitoring klaim
        Route::get('additional-task-claims', [\App\Http\Controllers\Web\UnitHead\AdditionalTaskClaimController::class, 'index'])->name('additional_task_claims.index');

        // Review & validasi penilaian – Level 2
        Route::get('assessments/pending',   [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::post('assessments/{assessment}/approve', [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject',  [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');
    });

/**
 * =========================
 * KEPALA POLIKLINIK AREA
 * =========================
 * Tugas:
 * - Approval final bobot kriteria (unit_criteria_weights) untuk area poliklinik
 * - Approval final penilaian kinerja – Level 3
 * - Monitoring remunerasi unit-unit poliklinik
 */
Route::middleware(['auth','verified','role:kepala_poliklinik'])
    ->prefix('kepala-poliklinik')->name('kepala_poliklinik.')->group(function () {

        // Approval bobot kriteria unit
        Route::get('unit-criteria-weights', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'index'])->name('unit_criteria_weights.index');
        Route::post('unit-criteria-weights/{weight}/approve', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'approve'])->name('unit_criteria_weights.approve');
        Route::post('unit-criteria-weights/{weight}/reject',  [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'reject'])->name('unit_criteria_weights.reject');
    // Read-only list per unit & detail
    Route::get('unit-criteria-weights/units', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'units'])->name('unit_criteria_weights.units');
    Route::get('unit-criteria-weights/units/{unitId}', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'unit'])->name('unit_criteria_weights.unit');

        // Approval final penilaian – Level 3
        Route::get('assessments/pending',   [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::post('assessments/{assessment}/approve', [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject',  [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');

        // Monitoring remunerasi
        Route::get('remunerations', [\App\Http\Controllers\Web\PolyclinicHead\RemunerationMonitorController::class, 'index'])->name('remunerations.index');
        Route::get('remunerations/{remuneration}', [\App\Http\Controllers\Web\PolyclinicHead\RemunerationMonitorController::class, 'show'])->name('remunerations.show');
    });

/**
 * =========================
 * PEGAWAI MEDIS (opsional – untuk kelengkapan alur)
 * =========================
 * - Submit & lihat penilaian kinerja (performance_assessments, performance_assessment_details)
 * - Klaim tugas tambahan & submit hasil (additional_task_claims, additional_contributions)
 * - Lihat remunerasi pribadi
 */
Route::middleware(['auth','verified','role:pegawai_medis'])
    ->prefix('pegawai-medis')->name('pegawai_medis.')->group(function () {

        // Penilaian kinerja: hanya index & show (dibuat oleh sistem)
        Route::resource('assessments', \App\Http\Controllers\Web\MedicalStaff\PerformanceAssessmentController::class)
            ->only(['index','show']);

        // Klaim & tugas tambahan
        Route::post('additional-tasks/{task}/claim',    [\App\Http\Controllers\Web\MedicalStaff\AdditionalTaskClaimController::class, 'claim'])->name('additional_tasks.claim');
        Route::post('additional-task-claims/{claim}/cancel', [\App\Http\Controllers\Web\MedicalStaff\AdditionalTaskClaimController::class, 'cancel'])->name('additional_task_claims.cancel');
        Route::post('additional-task-claims/{claim}/complete',[\App\Http\Controllers\Web\MedicalStaff\AdditionalTaskClaimController::class, 'complete'])->name('additional_task_claims.complete');

        // Submit hasil kontribusi (bukti/evidence)
        Route::resource('additional-contributions', \App\Http\Controllers\Web\MedicalStaff\AdditionalContributionController::class);

        // Lihat remunerasi pribadi
        Route::get('remunerations',       [\App\Http\Controllers\Web\MedicalStaff\RemunerationController::class, 'index'])->name('remunerations.index');
        Route::get('remunerations/{id}',  [\App\Http\Controllers\Web\MedicalStaff\RemunerationController::class, 'show'])->name('remunerations.show');

        // Data remunerasi (versi internal untuk pegawai medis) – pindahan dari halaman publik
        Route::get('remuneration-data', [\App\Http\Controllers\Web\MedicalStaff\RemunerationDataController::class, 'index'])->name('remuneration_data.index');

        // Lihat kriteria & bobot aktif untuk unit sendiri pada periode aktif
        Route::get('unit-criteria-weights', [\App\Http\Controllers\Web\MedicalStaff\UnitCriteriaWeightViewController::class, 'index'])->name('unit_criteria_weights.index');
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
