<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;

// Public pages 
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\FaqController;
use App\Http\Controllers\Web\AboutPageController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\PublicReviewController;
use App\Http\Controllers\Web\ReviewInvitationRedirectController;

// Dashboards per role
use App\Http\Controllers\Web\SuperAdmin\DashboardController as SADashboard;
use App\Http\Controllers\Web\UnitHead\DashboardController as KUDashboard;             // kepala_unit
use App\Http\Controllers\Web\UnitHead\ReviewApprovalController;
use App\Http\Controllers\Web\AdminHospital\DashboardController as AdminDashboard;             // admin_rs (baru)
use App\Http\Controllers\Web\MedicalStaff\DashboardController as PMDashboard;         // pegawai_medis
use App\Http\Controllers\Web\PolyclinicHead\DashboardController as KPDashboard;       // kepala_poliklinik
use App\Http\Controllers\Web\MultiRater\StoreController as MRStoreController;

Route::get('/', [HomeController::class, 'index'])->name('home');

/**
 * Public, English slugs
 */
Route::get('/announcements',        [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{slug}', [AnnouncementController::class, 'show'])->name('announcements.show');

Route::get('/faqs',                 [FaqController::class, 'index'])->name('faqs.index');

Route::get('/about-pages/{type}',   [AboutPageController::class, 'show'])->name('about_pages.show');
Route::get('/contact',              [ContactController::class, 'index'])->name('contact');


// Invitation-based public reviews (one-time token)
Route::get('/r/{token}', ReviewInvitationRedirectController::class)
    ->middleware('throttle:review-invite')
    ->name('reviews.invite.short');

Route::get('/reviews/invite/{token}', [PublicReviewController::class, 'show'])
    ->middleware('throttle:review-invite')
    ->name('reviews.invite.show');
Route::post('/reviews/invite/{token}', [PublicReviewController::class, 'store'])
    ->middleware('throttle:review-submit')
    ->name('reviews.invite.store');

Route::get('/reviews/thanks', [PublicReviewController::class, 'thanks'])
    ->name('reviews.thanks');

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
        // Bulk user import
        Route::get('users-import', [\App\Http\Controllers\Web\SuperAdmin\UserImportController::class, 'form'])->name('users.import.form');
        Route::post('users-import', [\App\Http\Controllers\Web\SuperAdmin\UserImportController::class, 'process'])->name('users.import.process');
        Route::get('users-import-template', [\App\Http\Controllers\Web\SuperAdmin\UserImportController::class, 'template'])->name('users.import.template');

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
        Route::post('attendances/import/preview',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'preview'])->name('attendances.import.preview');
        Route::get('attendances/import-template',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'template'])->name('attendances.import.template');
        Route::get('attendances/batches',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'index'])->name('attendances.batches');
        Route::get('attendances/batches/{batch}',[\App\Http\Controllers\Web\AdminHospital\AttendanceImportController::class, 'show'])->name('attendances.batches.show');
        Route::resource('attendances',   \App\Http\Controllers\Web\AdminHospital\AttendanceController::class)->only(['index','show','update','destroy']);

        // Multi-Level Assessment Approvals – Level 1 (Admin RS)
        Route::get('assessments/pending',             [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::get('assessments/{assessment}/detail', [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'detail'])->name('assessments.detail');
        Route::post('assessments/{assessment}/approve',[\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject', [\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');
        Route::post('assessments/{assessment}/resubmit',[\App\Http\Controllers\Web\AdminHospital\AssessmentApprovalController::class, 'resubmit'])->name('assessments.resubmit');

        // Alokasi Unit untuk remunerasi
        Route::resource('unit-remuneration-allocations', \App\Http\Controllers\Web\AdminHospital\UnitRemunerationAllocationController::class)
            ->parameters(['unit-remuneration-allocations' => 'allocation']);

        // Proses & Publikasi Remunerasi
        Route::get('remunerations/calc',        [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'calcIndex'])->name('remunerations.calc.index');
        Route::post('remunerations/calc/run',   [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'runCalculation'])->name('remunerations.calc.run');
        Route::post('remunerations/calc/audit', [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'auditCalculation'])->name('remunerations.calc.audit');
        Route::post('remunerations/{remuneration}/publish', [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'publish'])->name('remunerations.publish');
        Route::post('remunerations/publish-all', [\App\Http\Controllers\Web\AdminHospital\RemunerationController::class, 'publishAll'])->name('remunerations.publish_all');
        Route::resource('remunerations', \App\Http\Controllers\Web\AdminHospital\RemunerationController::class)->only(['index','show','update']);

        // Kinerja (dipindahkan dari Super Admin)
        Route::resource('performance-criterias', \App\Http\Controllers\Web\AdminHospital\PerformanceCriteriaController::class);

        // Manual Criteria Metrics (input + upload CSV)
        Route::get('metrics', [\App\Http\Controllers\Web\AdminHospital\CriteriaMetricsController::class, 'index'])->name('metrics.index');
        Route::get('metrics/create', [\App\Http\Controllers\Web\AdminHospital\CriteriaMetricsController::class, 'create'])->name('metrics.create');
        Route::post('metrics', [\App\Http\Controllers\Web\AdminHospital\CriteriaMetricsController::class, 'store'])->name('metrics.store');
        Route::post('metrics/template', [\App\Http\Controllers\Web\AdminHospital\CriteriaMetricsController::class, 'downloadTemplate'])->name('metrics.template');
        Route::post('metrics/upload-csv', [\App\Http\Controllers\Web\AdminHospital\CriteriaMetricsController::class, 'uploadCsv'])->name('metrics.upload_csv');

        // Review Invitations (import invitation links from Excel)
        Route::get('review-invitations', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationController::class, 'index'])
            ->name('review_invitations.index');
        Route::post('review-invitations/{id}/send-email', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationController::class, 'sendEmail'])
            ->name('review_invitations.send_email');
        Route::post('review-invitations/send-email-bulk', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationController::class, 'sendEmailBulk'])
            ->name('review_invitations.send_email_bulk');
        Route::post('review-invitations/test-email', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationController::class, 'testEmail'])
            ->name('review_invitations.test_email');
        Route::get('review-invitations/import', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationImportController::class, 'form'])
            ->name('review_invitations.import.form');
        Route::post('review-invitations/import', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationImportController::class, 'process'])
            ->name('review_invitations.import.process');
        Route::get('review-invitations/import/export', [\App\Http\Controllers\Web\AdminHospital\ReviewInvitationImportController::class, 'exportCsv'])
            ->name('review_invitations.import.export');

        // 360 Invitations management (URL diselaraskan dengan folder view: admin_rs/multi_rater)
        Route::get('multi-rater', [\App\Http\Controllers\Web\AdminHospital\MultiRaterController::class, 'index'])->name('multi_rater.index');
        Route::post('multi-rater/open', [\App\Http\Controllers\Web\AdminHospital\MultiRaterController::class, 'openWindow'])->name('multi_rater.open');
        Route::post('multi-rater/close', [\App\Http\Controllers\Web\AdminHospital\MultiRaterController::class, 'closeWindow'])->name('multi_rater.close');
        Route::post('multi-rater/generate', [\App\Http\Controllers\Web\AdminHospital\MultiRaterController::class, 'generate'])->name('multi_rater.generate');

        // Aturan Kriteria 360 (criteria_rater_rules)
        Route::get('criteria-rater-rules', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'index'])->name('criteria_rater_rules.index');
        Route::get('criteria-rater-rules/create', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'create'])->name('criteria_rater_rules.create');
        Route::post('criteria-rater-rules', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'store'])->name('criteria_rater_rules.store');
        Route::get('criteria-rater-rules/{criteria_rater_rule}/edit', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'edit'])->name('criteria_rater_rules.edit');
        Route::put('criteria-rater-rules/{criteria_rater_rule}', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'update'])->name('criteria_rater_rules.update');
        Route::delete('criteria-rater-rules/{criteria_rater_rule}', [\App\Http\Controllers\Web\AdminHospital\CriteriaRaterRuleController::class, 'destroy'])->name('criteria_rater_rules.destroy');

    // Approval usulan kriteria baru
    Route::get('criteria-proposals', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'index'])->name('criteria_proposals.index');
    Route::post('criteria-proposals/{proposal}/approve', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'approve'])->name('criteria_proposals.approve');
    Route::post('criteria-proposals/{proposal}/reject', [\App\Http\Controllers\Web\AdminHospital\CriteriaProposalApprovalController::class,'reject'])->name('criteria_proposals.reject');

        // Periode Penilaian (assessment_periods)
        Route::resource('assessment-periods', \App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class)
            ->parameters(['assessment-periods' => 'period']);
        Route::post('assessment-periods/{period}/lock',     [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'lock'])->name('assessment_periods.lock');
        Route::post('assessment-periods/{period}/start-approval', [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'startApproval'])->name('assessment_periods.start_approval');
        Route::post('assessment-periods/{period}/open-revision', [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'openRevision'])->name('assessment_periods.open_revision');
        Route::post('assessment-periods/{period}/resubmit-from-revision', [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'resubmitFromRevision'])->name('assessment_periods.resubmit_from_revision');
        Route::post('assessment-periods/{period}/close',    [\App\Http\Controllers\Web\AdminHospital\AssessmentPeriodController::class, 'close'])->name('assessment_periods.close');

        // Bobot Kriteria Unit (setup awal/push draft per unit)
        Route::resource('unit-criteria-weights', \App\Http\Controllers\Web\AdminHospital\UnitCriteriaWeightController::class)
            ->only(['index','store','update','destroy','show']);
        Route::post('unit-criteria-weights/publish-draft', [\App\Http\Controllers\Web\AdminHospital\UnitCriteriaWeightController::class, 'publishDraft'])
            ->name('unit_criteria_weights.publish_draft');

        // Konten operasional (opsional) — tidak dikelola oleh Admin RS
    });

/**
 * =========================
 * ADMIN MASTER DATA (Admin RS)
 * =========================
 * URL mengikuti kebutuhan: /admin/master/...
 */
Route::middleware(['auth','verified','role:admin_rs'])
    ->prefix('admin/master')->name('admin.master.')->group(function () {

        Route::get('profession-hierarchy', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'index'])
            ->name('profession_hierarchy.index');
        Route::get('profession-hierarchy/create', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'create'])
            ->name('profession_hierarchy.create');
        Route::post('profession-hierarchy', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'store'])
            ->name('profession_hierarchy.store');
        Route::get('profession-hierarchy/{professionReportingLine}/edit', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'edit'])
            ->name('profession_hierarchy.edit');
        Route::put('profession-hierarchy/{professionReportingLine}', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'update'])
            ->name('profession_hierarchy.update');
        Route::post('profession-hierarchy/{professionReportingLine}/toggle', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'toggle'])
            ->name('profession_hierarchy.toggle');
        Route::delete('profession-hierarchy/{professionReportingLine}', [\App\Http\Controllers\Web\AdminHospital\ProfessionHierarchyController::class, 'destroy'])
            ->name('profession_hierarchy.destroy');
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
        Route::post('unit-criteria-weights/request-change', [\App\Http\Controllers\Web\UnitHead\UnitCriteriaWeightController::class, 'requestChange'])
            ->name('unit_criteria_weights.request_change');
        Route::post('unit-criteria-weights/copy-previous', [\App\Http\Controllers\Web\UnitHead\UnitCriteriaWeightController::class, 'copyFromPrevious'])
            ->name('unit_criteria_weights.copy_previous');

        // Usulan kriteria baru
        Route::get('criteria-proposals', [\App\Http\Controllers\Web\UnitHead\CriteriaProposalController::class,'index'])->name('criteria_proposals.index');
        Route::post('criteria-proposals', [\App\Http\Controllers\Web\UnitHead\CriteriaProposalController::class,'store'])->name('criteria_proposals.store');

        // Tugas Tambahan untuk unit
        Route::resource('additional-tasks', \App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class);
        Route::patch('additional-tasks/{task}/open',     [\App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class,'open'])->name('additional_tasks.open');
        Route::patch('additional-tasks/{task}/close',    [\App\Http\Controllers\Web\UnitHead\AdditionalTaskController::class,'close'])->name('additional_tasks.close');

        // Monitoring klaim
        Route::get('additional-task-claims', [\App\Http\Controllers\Web\UnitHead\AdditionalTaskClaimController::class, 'index'])->name('additional_task_claims.index');

        // Approval ulasan pasien publik
        Route::get('reviews', [ReviewApprovalController::class, 'index'])->name('reviews.index');
        Route::post('reviews/approve-all', [ReviewApprovalController::class, 'approveAll'])->name('reviews.approve-all');
        Route::post('reviews/{review}/approve', [ReviewApprovalController::class, 'approve'])->name('reviews.approve');
        Route::post('reviews/{review}/reject', [ReviewApprovalController::class, 'reject'])->name('reviews.reject');

        // Review klaim tugas tambahan (submitted -> approved/rejected)
        Route::get('additional-task-claims/review', [\App\Http\Controllers\Web\UnitHead\AdditionalTaskClaimReviewController::class,'index'])->name('additional_task_claims.review_index');
        Route::post('additional-task-claims/{claim}/review', [\App\Http\Controllers\Web\UnitHead\AdditionalTaskClaimReviewController::class,'update'])->name('additional_task_claims.review_update');

        // Review & validasi penilaian – Level 2
        Route::get('assessments/pending',   [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::get('assessments/{assessment}/detail', [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'detail'])->name('assessments.detail');
        Route::post('assessments/{assessment}/approve', [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject',  [\App\Http\Controllers\Web\UnitHead\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');

        // 360 submissions (if assigned as assessor) – selaraskan dengan multi-rater
        Route::get('multi-rater', [\App\Http\Controllers\Web\UnitHead\MultiRaterSubmissionController::class, 'index'])->name('multi_rater.index');
        // Place the specific store route BEFORE the parameterized route to avoid binding 'store' as {assessment}
        Route::post('multi-rater/store', [MRStoreController::class, 'store'])->name('multi_rater.store');
        Route::get('multi-rater/{assessment}', [\App\Http\Controllers\Web\UnitHead\MultiRaterSubmissionController::class, 'show'])->name('multi_rater.show');
        Route::post('multi-rater/{assessment}', [\App\Http\Controllers\Web\UnitHead\MultiRaterSubmissionController::class, 'submit'])->name('multi_rater.submit');

        // Bobot Penilai 360 (rater_weights) – draft & submit
        Route::get('rater-weights', [\App\Http\Controllers\Web\UnitHead\RaterWeightController::class, 'index'])->name('rater_weights.index');
        Route::put('rater-weights/{raterWeight}', [\App\Http\Controllers\Web\UnitHead\RaterWeightController::class, 'updateInline'])->name('rater_weights.update');
        Route::post('rater-weights/cek', [\App\Http\Controllers\Web\UnitHead\RaterWeightController::class, 'bulkCheck'])->name('rater_weights.cek');
        Route::post('rater-weights/copy-previous', [\App\Http\Controllers\Web\UnitHead\RaterWeightController::class, 'copyFromPrevious'])->name('rater_weights.copy_previous');
        Route::post('rater-weights/submit-all', [\App\Http\Controllers\Web\UnitHead\RaterWeightController::class, 'submitAll'])->name('rater_weights.submit_all');
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
        Route::post('unit-criteria-weights/approve-all', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'approveAll'])->name('unit_criteria_weights.approve_all');
        Route::post('unit-criteria-weights/{weight}/approve', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'approve'])->name('unit_criteria_weights.approve');
        Route::post('unit-criteria-weights/{weight}/reject',  [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'reject'])->name('unit_criteria_weights.reject');
        Route::post('unit-criteria-weights/units/{unitId}/approve', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'approveUnit'])->name('unit_criteria_weights.approve_unit');
        Route::post('unit-criteria-weights/units/{unitId}/reject',  [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'rejectUnit'])->name('unit_criteria_weights.reject_unit');
    // Read-only list per unit & detail
    Route::get('unit-criteria-weights/units', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'units'])->name('unit_criteria_weights.units');
    Route::get('unit-criteria-weights/units/{unitId}', [\App\Http\Controllers\Web\PolyclinicHead\UnitCriteriaApprovalController::class, 'unit'])->name('unit_criteria_weights.unit');

        // Approval final penilaian – Level 3
        Route::get('assessments/pending',   [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'index'])->name('assessments.pending');
        Route::get('assessments/{assessment}/detail', [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'detail'])->name('assessments.detail');
        Route::post('assessments/{assessment}/approve', [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'approve'])->name('assessments.approve');
        Route::post('assessments/{assessment}/reject',  [\App\Http\Controllers\Web\PolyclinicHead\AssessmentApprovalController::class, 'reject'])->name('assessments.reject');

        // Monitoring remunerasi
        Route::get('remunerations', [\App\Http\Controllers\Web\PolyclinicHead\RemunerationMonitorController::class, 'index'])->name('remunerations.index');
        Route::get('remunerations/{remuneration}', [\App\Http\Controllers\Web\PolyclinicHead\RemunerationMonitorController::class, 'show'])->name('remunerations.show');

        // 360 submissions (if assigned as assessor)
        Route::get('multi-rater', [\App\Http\Controllers\Web\PolyclinicHead\MultiRaterSubmissionController::class, 'index'])->name('multi_rater.index');
        // Specific store route BEFORE parameterized route
        Route::post('multi-rater/store', [MRStoreController::class, 'store'])->name('multi_rater.store');
        Route::get('multi-rater/{assessment}', [\App\Http\Controllers\Web\PolyclinicHead\MultiRaterSubmissionController::class, 'show'])->name('multi_rater.show');
        Route::post('multi-rater/{assessment}', [\App\Http\Controllers\Web\PolyclinicHead\MultiRaterSubmissionController::class, 'submit'])->name('multi_rater.submit');

        // Bobot Penilai 360 (rater_weights) – approval
        Route::get('rater-weights', [\App\Http\Controllers\Web\PolyclinicHead\RaterWeightApprovalController::class, 'index'])->name('rater_weights.index');
        Route::post('rater-weights/units/{unitId}/approve', [\App\Http\Controllers\Web\PolyclinicHead\RaterWeightApprovalController::class, 'approveUnit'])->name('rater_weights.approve_unit');
        Route::post('rater-weights/units/{unitId}/reject', [\App\Http\Controllers\Web\PolyclinicHead\RaterWeightApprovalController::class, 'rejectUnit'])->name('rater_weights.reject_unit');
        Route::post('rater-weights/{raterWeight}/approve', [\App\Http\Controllers\Web\PolyclinicHead\RaterWeightApprovalController::class, 'approve'])->name('rater_weights.approve');
        Route::post('rater-weights/{raterWeight}/reject', [\App\Http\Controllers\Web\PolyclinicHead\RaterWeightApprovalController::class, 'reject'])->name('rater_weights.reject');
    });

/**
 * =========================
 * PEGAWAI MEDIS (opsional – untuk kelengkapan alur)
 * =========================
 * - Submit & lihat penilaian kinerja (performance_assessments, performance_assessment_details)
 * - Klaim tugas tambahan & submit hasil (additional_task_claims)
 * - Lihat remunerasi pribadi
 */
Route::middleware(['auth','verified','role:pegawai_medis'])
    ->prefix('pegawai-medis')->name('pegawai_medis.')->group(function () {

        // Kinerja Saya (periode berjalan)
        Route::get('my-performance', [\App\Http\Controllers\Web\MedicalStaff\MyPerformanceController::class, 'index'])
            ->name('my_performance.index');

        // 360 submissions (place BEFORE resource to avoid route conflicts) – seragam: multi-rater
        Route::get('multi-rater', [\App\Http\Controllers\Web\MedicalStaff\MultiRaterSubmissionController::class, 'index'])->name('multi_rater.index');
        // Specific store route BEFORE parameterized route
        Route::post('multi-rater/store', [MRStoreController::class, 'store'])->name('multi_rater.store');
        Route::get('multi-rater/{assessment}', [\App\Http\Controllers\Web\MedicalStaff\MultiRaterSubmissionController::class, 'show'])->name('multi_rater.show');
        Route::post('multi-rater/{assessment}', [\App\Http\Controllers\Web\MedicalStaff\MultiRaterSubmissionController::class, 'submit'])->name('multi_rater.submit');

        // Penilaian kinerja: hanya index & show (dibuat oleh sistem)
        // Debug route: keep detailed UI accessible separately.
        Route::get('assessments/ariel/{assessment}', [\App\Http\Controllers\Web\MedicalStaff\PerformanceAssessmentController::class, 'showAriel'])
            ->name('assessments.show_ariel');

        Route::resource('assessments', \App\Http\Controllers\Web\MedicalStaff\PerformanceAssessmentController::class)
            ->only(['index','show']);

        // Klaim & tugas tambahan
        Route::get('additional-tasks', [\App\Http\Controllers\Web\MedicalStaff\AdditionalTaskController::class, 'index'])->name('additional_tasks.index');
        Route::post('additional-tasks/{task}/submit', [\App\Http\Controllers\Web\MedicalStaff\AdditionalTaskClaimController::class, 'submit'])->name('additional_tasks.submit');

        // Lihat remunerasi pribadi
        Route::get('remunerations',       [\App\Http\Controllers\Web\MedicalStaff\RemunerationController::class, 'index'])->name('remunerations.index');
        Route::get('remunerations/{id}',  [\App\Http\Controllers\Web\MedicalStaff\RemunerationController::class, 'show'])->name('remunerations.show');

        // Lihat kriteria & bobot aktif untuk unit sendiri pada periode aktif
        Route::get('unit-criteria-weights', [\App\Http\Controllers\Web\MedicalStaff\UnitCriteriaWeightViewController::class, 'index'])->name('unit_criteria_weights.index');

        // (moved to assessments-360 above to avoid collision with resource route)
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

/**
 * Role switching (multi-role users)
 */
Route::middleware(['auth','verified'])->post('/switch-role', [\App\Http\Controllers\Auth\ActiveRoleController::class, 'update'])
    ->name('auth.switch-role');
