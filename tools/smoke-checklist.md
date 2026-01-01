# Manual Smoke Checklist (Post-Refactor)

Tanggal: 2025-12-27

> Tujuan: verifikasi cepat alur utama tanpa bergantung pada test otomatis.

## 1) Boot & Auth
- Buka `/` (Home) dan pastikan halaman tampil.
- Klik login (modal) dan login sebagai masing-masing role: `super_admin`, `admin_rs`, `kepala_unit`, `pegawai_medis`, `kepala_poliklinik`.
- Pastikan switch role (jika tersedia) bekerja dan session `active_role` berubah.

## 2) Admin RS (alur utama)
- Masuk `admin-rs/dashboard`.
- Buka `admin-rs/assessment-periods`:
  - Buat periode baru.
  - Aktifkan periode (`activate`) dan pastikan status berubah.
  - Coba lock (`lock`) dan start approval (`start-approval`) sesuai kebutuhan.
- Buka `admin-rs/unit-criteria-weights`:
  - Buat/push draft bobot kriteria untuk 1 unit.
  - Publish draft (jika digunakan) dan pastikan status tersimpan.
- Buka `admin-rs/remunerations/calc`:
  - Jalankan kalkulasi (jika aman di env dev) dan pastikan tidak error.

## 3) Kepala Unit
- Masuk `kepala-unit/additional-tasks`:
  - Buat tugas tambahan dengan salah satu: `bonus_amount` ATAU `points`.
  - Pastikan validasi menolak jika keduanya diisi sekaligus.
  - Set penalty percent > 100 dan pastikan ditolak.
- Buka monitoring klaim `kepala-unit/additional-task-claims` dan pastikan list tampil.

## 4) Pegawai Medis
- Masuk `pegawai-medis/additional-tasks`:
  - Claim satu task.
  - Cancel sebelum deadline: status `cancelled`, `is_violation=false`.
  - Cancel setelah deadline: status `cancelled`, `is_violation=true`, `penalty_applied` tetap `false`.
- Submit hasil claim (PDF) dan pastikan upload/validasi sukses.

## 5) Multi-Rater (360)
- Pastikan ada `assessment_period` ACTIVE dan window 360 aktif.
- Hit endpoint store JSON (via UI atau Postman) `*/multi-rater/store`:
  - Submit score 1..100 untuk criteria 360.
  - Pastikan response JSON `ok=true`.

## 6) Public Pages (read-only)
- Buka `/announcements` dan `/faqs`.
- Pastikan halaman tidak error (konten boleh kosong).

## 7) Validasi Skor WSM vs Excel (TOTAL_UNIT)

Tujuan: memastikan hasil normalisasi + WSM yang tampil/tersimpan sama dengan Excel template uji.

**Prasyarat**
- File Excel template tersedia: `storage/app/testing/excel/template_uji_wsm_total_unit.xlsx`.
- Sudah ada `unit_criteria_weights` untuk unit target + periode target, dan status bobot sudah sesuai aturan:
  - Periode ACTIVE: pakai `status=active`
  - Periode non-ACTIVE: prefer `status=active`, fallback `status=archived`

**Langkah (DB → Service → UI)**
1) Seed data mentah (jika perlu data uji yang konsisten):
   - Jalankan `php artisan db:seed --class=FiveStaffKpiSeeder`
   - Pastikan seeder hanya mengisi data mentah (absensi/360/kontribusi/metric/rating) dan tidak menghitung skor.

2) Recalculate skor periode (membuat/meng-update `performance_assessments` + `performance_assessment_details`):
   - Via tinker:
     - `php artisan tinker`
     - `app(App\Services\PeriodPerformanceAssessmentService::class)->recalculateForPeriodId(<period_id>);`

3) Bandingkan hasil DB dengan Excel (TOTAL_UNIT):
   - Ambil skor tersimpan:
     - `SELECT user_id, total_wsm_score FROM performance_assessments WHERE assessment_period_id=<period_id> ORDER BY user_id;`
     - `SELECT performance_assessment_id, performance_criteria_id, score FROM performance_assessment_details WHERE performance_assessment_id IN (...) ORDER BY performance_assessment_id, performance_criteria_id;`
   - Cocokkan terhadap sheet hasil di Excel template untuk:
     - `nilai_normalisasi` per kriteria
     - `total_wsm` per user

4) Bandingkan output service baru langsung (ini sumber kebenaran perhitungan):
   - Via tinker:
     - `$period = App\Models\AssessmentPeriod::find(<period_id>);`
     - `$userIds = App\Models\User::role('pegawai_medis')->where('unit_id', <unit_id>)->pluck('id')->all();`
     - `$out = app(App\Services\PerformanceScore\PerformanceScoreService::class)->calculate(<unit_id>, $period, $userIds, null);`
   - Cocokkan `out['users'][<uid>]['criteria'][*]['nilai_normalisasi']` dan `total_wsm` dengan Excel.

**Poin PASS**
- Normalisasi mengikuti `performance_criterias.normalization_basis` (TOTAL_UNIT) dan hasilnya sama dengan Excel.
- `total_wsm` = Σ(bobot × nilai_normalisasi) / Σ(bobot) dan bobot diambil dari `unit_criteria_weights`.

## 8) Validasi COST menurunkan nilai WSM

Tujuan: memastikan kriteria bertipe COST benar-benar memberi efek "penalti" (nilai WSM turun ketika cost naik).

1) Pilih satu kriteria COST (contoh: `Jumlah Komplain Pasien` atau `Keterlambatan (Absensi)` jika bertipe cost).
2) Catat `total_wsm_score` user A sebelum perubahan.
3) Naikkan nilai cost user A pada data mentah (misal tambah `imported_criteria_values.value_numeric` untuk komplain, atau tambah `attendances.late_minutes`).
4) Jalankan recalculation periode.
5) **PASS jika** `nilai_normalisasi` untuk kriteria COST user A turun (atau minimal tidak naik), dan `total_wsm_score` user A ikut turun (dengan bobot > 0).

## 9) Validasi skor berubah jika data mentah diubah

Tujuan: memastikan pipeline reaktif terhadap perubahan data mentah.

1) Pilih 1 user + 1 kriteria BENEFIT (misal `Jumlah Pasien Ditangani`).
2) Tambah data mentah (misal tambah 10 pasien di `imported_criteria_values`).
3) Jalankan recalculation periode.
4) **PASS jika** `nilai_normalisasi` dan/atau `total_wsm_score` berubah sesuai ekspektasi (tergantung basis & bobot).

## 10) Pastikan tidak ada kriteria hardcode

Tujuan: memastikan daftar kriteria, bobot, dan basis normalisasi tidak ditentukan statis di kode.

Checklist:
- Tidak ada pemakaian `BestScenarioCalculator` dalam flow penilaian / perhitungan skor.
- Kriteria yang dihitung ditentukan oleh `unit_criteria_weights` (bukan array nama/ID di kode).
- `normalization_basis` dibaca dari kolom `performance_criterias.normalization_basis` (bukan if/else hardcode per kriteria).
- Bobot WSM dibaca dari `unit_criteria_weights.weight`.

Cara cepat:
- Cari referensi `BestScenarioCalculator` dan pastikan hanya tersisa class deprecated.
- Cari pola `DEFAULT_WEIGHT` / daftar kriteria statis untuk WSM; tidak boleh dipakai untuk hasil produksi.

## 11) Ringkasan: Alur data → skor → WSM

- Data mentah masuk ke tabel sumber:
  - Absensi: `attendances`
  - 360: `multi_rater_assessments` + `multi_rater_assessment_details`
  - Kontribusi: `additional_contributions`
  - Metric import: `imported_criteria_values`
  - Rating: `reviews` + `review_details`
- Collector meng-agregasi raw per user+periode+unit.
- Normalisasi dihitung per kriteria mengikuti `normalization_basis` + tipe `benefit/cost`.
- Total WSM dihitung dari bobot `unit_criteria_weights`:
  - `total_wsm = Σ(bobot × nilai_normalisasi) / Σ(bobot)`
  - Kriteria `is_active=false` tetap bisa tampil, tapi tidak masuk WSM.

## 12) Kenapa hasil sekarang berbeda dari BestScenarioCalculator

- BestScenarioCalculator memakai asumsi/bobot statis dan normalisasi sederhana (best-scenario), sehingga sering membuat nilai relatif 100 atau tidak sensitif terhadap konfigurasi DB.
- Flow baru membaca:
  - kriteria aktif dari `unit_criteria_weights`
  - kebijakan normalisasi dari `performance_criterias.normalization_basis`
  - tipe COST/BENEFIT dari `performance_criterias.type`
  sehingga hasil mengikuti Excel dan berubah sesuai data mentah + konfigurasi periode/unit.
