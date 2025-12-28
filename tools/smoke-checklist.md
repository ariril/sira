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
