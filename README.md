<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Multi-Role Implementation (Project Specific)

This application extends the default Laravel auth model to allow each user to hold multiple roles simultaneously.

### Schema Changes
- `roles` table: defines role records (`slug`, `name`).
- `role_user` pivot: many-to-many between users and roles.
- `users.last_role`: remembers the last active role for a user.
- Removed legacy single `users.role` column (queries must use pivot now).

### Active Role Resolution
The active role used for authorization/UI is determined in order:
1. `session('active_role')` if present and user still has that role.
2. `users.last_role` if valid.
3. Priority fallback sequence: super_admin → admin_rs → kepala_poliklinik → kepala_unit → pegawai_medis.
4. First attached role if none of the above apply.

Helper: `$user->getActiveRoleSlug()` (also exposed as `$user->role` for backward compatibility) and `$user->role_label` for display.

### Eloquent Helpers
Added to `App\Models\User`:
- `roles()` relation.
- Scope: `scopeRole($query, $slug)` and `scopeRoles($query, $slugsArray)`.
- Checks: `hasRole($slug)`, `hasAnyRole($array)`, `hasAllRoles($array)`, `listRoleSlugs()`.

### Blade Directives
Registered in `AppServiceProvider`:
```php
@role('super_admin') ... @endrole
@anyrole('admin_rs','super_admin') ... @endanyrole
@allroles('kepala_unit','pegawai_medis') ... @endallroles
```

### Session Role Switching
Dropdown in `partials/nonpublic/navigation.blade.php` allows multi-role users to switch roles (`POST /switch-role`) updating the session and persisting `last_role`.

### Refactoring Notes
All former usages of `where('role', ...)` or `users.role` replaced with pivot-based queries:
```php
User::query()->role('pegawai_medis')->get();
// or manual when using Query Builder:
->whereExists(function($q){
		$q->select(DB::raw(1))
			->from('role_user as ru')
			->join('roles as r','r.id','=','ru.role_id')
			->whereColumn('ru.user_id','users.id')
			->where('r.slug','pegawai_medis');
})
```

### Seeder
Primary user and sample accounts attach multiple roles with `$user->roles()->attach([...])`.

## Bobot Penilai 360 (Rater Weights)

Mekanisme bobot penilaian 360 terdiri dari 2 lapis:

1) Bobot kriteria unit ("Bobot per Unit")
- Menentukan kontribusi tiap kriteria (mis. Kedisiplinan, Kerjasama, dst) ke skor akhir.
- Disimpan di `unit_criteria_weights`.

2) Bobot penilai 360 ("Bobot Penilai 360")
- Menentukan komposisi penilai untuk setiap kriteria 360 per profesi assessee.
- Contoh: Kedisiplinan (360) untuk Dokter Umum = Atasan berapa %, Diri sendiri berapa %, dst.
- Aturan jenis penilai (self/supervisor/peer/subordinate) ditentukan oleh `criteria_rater_rules`.
- Baris bobot per unit+periode dibuat otomatis oleh `App\Services\RaterWeightGenerator::syncForUnitPeriod()` ke tabel `unit_rater_weights`.

Aturan penting:
- Jika sebuah kombinasi (periode, unit, kriteria, profesi assessee) hanya menghasilkan 1 baris penilai, sistem otomatis mengunci bobot 100%.
- Jika >1 baris penilai, bobot awalnya `NULL` dan harus diisi sampai total 100% sebelum bisa diajukan.

### Seeder Bobot November
Jika pada periode November ada kelompok bobot 360 yang belum diisi sama sekali (semua `NULL`), seeder ini akan mengisi bobot default secara merata (non-destructive: tidak menimpa nilai yang sudah diisi).

- Jalankan (sudah terintegrasi di `DatabaseSeeder`):
	- `php artisan db:seed -v`
- Atau paksa periode tertentu (by id) lalu jalankan seeding:
	- Windows PowerShell: `$env:RATER_WEIGHT_PERIOD_ID=123; php artisan db:seed -v`

### Updating Legacy Code
Search patterns to eliminate:
- `users.role`
- `->where('role', '...')`
- Blade conditions `auth()->user()->role === '...'`

Use helpers instead:
```php
auth()->user()->hasRole('pegawai_medis');
auth()->user()->hasAnyRole(['admin_rs','super_admin']);
```

### Why Keep Profession Separate?
`profession_id` remains a single classification (e.g., Dokter, Perawat) while roles govern access level and responsibilities. This separation avoids overloading the role system with HR taxonomy.

### Future Extension Ideas
- Cache role slug list per user to reduce pivot queries.
- Add permission matrix per role.
- Event dispatch on role switch for audit logging.

---

## KPI Excel as Source-of-Truth (Seeder)

Seeder demo KPI (`Database\\Seeders\\EightStaffKpiSeeder`) dapat mengambil konfigurasi bobot + data KPI dari Excel, supaya hasil perhitungan mengikuti template Excel.

- Default path: `storage/app/kpi-template.xlsx`
- Override path: set env `KPI_TEMPLATE_PATH`
- Enforce Excel (tanpa fallback hardcode): set env `KPI_REQUIRE_EXCEL=true`

Format Excel fleksibel (header dicocokkan via alias), tetapi minimal perlu:

- Sheet bobot unit: `period_name`, `unit_slug`, `criteria_name`, `weight` (+ opsional `status`)
- Sheet data KPI: `period_name`, salah satu dari `staff_key|email|employee_number`, lalu `attendance`, `discipline`, `contrib`, `patients`, `rating`
- Sheet kebijakan normalisasi: `criteria_name`, `normalization_basis` (+ opsional `custom_target_value`)

## Additional Task & Contribution Workflow (BPMN Mapping)

This section documents the custom workflow implemented for "Tugas Tambahan" and their evidence contributions, mapped from the BPMN provided.

### 1. Core Data Structures
- `additional_tasks` (`App\Models\AdditionalTask`): definisi tugas per unit (fields: `due_date`, `due_time`, `points`, `max_claims`, `status` = open|closed).
- `additional_task_claims` (`App\Models\AdditionalTaskClaim`): submission & review (statuses: `submitted|approved|rejected`, fields: `submitted_at`, `reviewed_at`, `review_comment`, `awarded_points`).
- `remunerations` (`App\Models\Remuneration`): final period remuneration.

### 2. Status Lifecycle (Claims)
```
open task → (pegawai submit) submitted → (kepala unit approve) approved
                              ↘ (kepala unit reject) rejected
```
Catatan: jika submit melewati deadline, klaim dapat dibuat sebagai `rejected` otomatis (awarded_points=0).

### 3. Primary Transitions & Methods
- Submit result: `POST /pegawai-medis/additional-tasks/{task}/submit` → membuat `AdditionalTaskClaim` status `submitted` (atau auto-`rejected` jika telat) + notify unit heads.
- Approve/Reject: `POST /kepala-unit/additional-task-claims/{claim}/review` action=approve|reject → mengisi `reviewed_at`, `review_comment`, dan `awarded_points`.

### 4. Contribution Evidence Flow
N/A (module ini hanya mendokumentasikan alur **Tugas Tambahan** points-only).

### 5. Notifications
| Event | Class | Trigger |
|-------|-------|---------|
| Claim submitted | `ClaimSubmittedNotification` | after submit |
| Claim approved | `ClaimApprovedNotification` | action=approve |
| Claim rejected | `ClaimRejectedNotification` | action=reject |

All notifications currently sent via `mail` channel and can be queued (classes implement `ShouldQueue` where appropriate).

### 6. Penalty Logic
Tidak ada penalty/cancel pada alur Tugas Tambahan (points-only).

### 7. Concurrency Safeguards
Claim creation uses DB transaction + `lockForUpdate()` when counting quota so `max_claims` isn’t oversubscribed under race conditions.

### 8. FormRequest Overview
| Purpose | File |
|---------|------|
| Create Task | `StoreAdditionalTaskRequest` |
| Update Task | `UpdateAdditionalTaskRequest` |
| Submit Task Result | `SubmitAdditionalTaskClaimResultRequest` |
| Review Claim | `ReviewAdditionalTaskClaimRequest` |

### 9. Tests
`tests/Feature/AdditionalTaskClaimTest.php` covers:
- Single claim quota enforcement.
- Late submit auto-reject.
- Full submit → approve/reject flow.

### 10. Remuneration Integration
`runCalculation()` adds `approved_contribution_bonus` per user into `amount` and records breakdown in `calculation_details` (`base_amount`, `approved_contribution_bonus`).

### 11. Extension Ideas
- Queue all notifications (`implements ShouldQueue`) & configure mail driver.
- Add audit trail table for each status transition (`claim_status_logs`).
- Add policy layer (`Gate/Policy`) instead of manual role checks for finer control.
- Introduce soft-deletion or archiving on tasks & claims after period close.

### 12. Quick Reference Endpoints
| Actor | Action | Route Name |
|-------|--------|------------|
| Pegawai Medis | List available tasks | `pegawai_medis.additional_tasks.index` |
| Pegawai Medis | Submit task result | `pegawai_medis.additional_tasks.submit` |
| Kepala Unit | Review claims list | `kepala_unit.additional_task_claims.review_index` |
| Kepala Unit | Approve/Reject claim | `kepala_unit.additional_task_claims.review_update` |
| Kepala Unit | Approve/Reject contribution | `kepala_unit.additional_contributions.approve/reject` |
| Kepala Unit | Download evidence | `kepala_unit.additional_contributions.download` |
| Admin RS | Run remuneration calc | `admin_rs.remunerations.calc.run` |

### 13. BPMN Alignment Notes
- Decision Gate: "Apakah tugas masih tersedia?" → enforced by transaction + max_claims check.
- Deadline Gate: "Apakah melewati batas waktu?" → submit dapat auto-ditolak jika telat.

---

---

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
