<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceImportBatch as Batch;
use App\Models\User;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AttendanceImportController extends Controller
{
    // Upload form
    public function create(Request $request): View
    {
        return view('admin_rs.attendances.import.create');
    }

    // Handle upload + import (CSV/XLS/XLSX)
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // izinkan csv, xls, xlsx
            'file' => ['required','file','mimes:csv,txt,xls,xlsx','max:5120'],
            'replace_existing' => ['nullable','boolean'],
        ]);

        $file = $validated['file'];

        $storedPath = $file->store('attendance_imports');
        $original   = $file->getClientOriginalName();

        $batch = null;
        $success = 0; $failed = 0; $total = 0;
        $reasonCounts = [
            'no_user'   => 0,
            'bad_date'  => 0,
            'bad_time'  => 0,
            'db_error'  => 0,
        ];

        DB::beginTransaction();
        try {
            // Baca sebagai array [header, rows]
            [$header, $rows] = $this->readTabularFile(Storage::path($storedPath), $file->getClientOriginalExtension());
            if (!$header) throw new \RuntimeException('File kosong atau header tidak ditemukan.');

            $map = $this->mapHeader($header);
            if (!$map) throw new \RuntimeException('Header tidak sesuai. Pastikan terdapat kolom NIP/employee_number dan Tanggal.');

            // Tentukan periode dari isi file (semua tanggal harus dalam bulan yang sama)
            [$periodId, $periodText, $prevActive] = $this->determinePeriodAndPrevious($map, $rows);
            if ($periodId === null) {
                throw new \RuntimeException('Rentang tanggal pada file mencakup lebih dari satu bulan. Pisahkan per bulan.');
            }
            if ($prevActive && !($validated['replace_existing'] ?? false)) {
                $info = sprintf('Periode %s sudah memiliki import sebelumnya (total=%d, berhasil=%d, gagal=%d). Centang kotak "Timpa import periode ini" untuk menimpa.',
                    $periodText, $prevActive->total_rows, $prevActive->success_rows, $prevActive->failed_rows);
                throw new \RuntimeException($info);
            }

            // Jika menimpa, tandai batch sebelumnya superseded TERLEBIH DAHULU
            // agar tidak melanggar unique (assessment_period_id, is_superseded=false)
            if ($prevActive) {
                $prevActive->update(['is_superseded' => true]);
            }

            $batch = Batch::create([
                'file_name'   => $original,
                'assessment_period_id' => $periodId,
                'previous_batch_id' => $prevActive?->id,
                'imported_by' => Auth::id(),
                'imported_at' => now(),
                'total_rows'  => 0,
                'success_rows'=> 0,
                'failed_rows' => 0,
            ]);

            // Setelah batch baru dibuat, hapus data absensi hasil impor lama (preview disimpan untuk audit)
            if ($prevActive) {
                Attendance::where('import_batch_id', $prevActive->id)->delete();
            }

            foreach ($rows as $idx => $row) {
                $total++;
                $data = $this->rowToAssoc($map, $row);
                if (!$data) {
                    $failed++;
                    $reasonCounts['db_error']++;
                    $this->recordPreviewRow($batch->id, $idx+2, null, $data, false, 'db_error', 'Baris tidak dapat dipetakan ke header.');
                    continue;
                }
                [$ok, $reasonOrUser] = $this->importRow($batch->id, $data);
                if ($ok) {
                    $success++;
                    $this->recordPreviewRow($batch->id, $idx+2, $reasonOrUser?->id, $data, true, null, null);
                } else {
                    $failed++;
                    $reason = $reasonOrUser;
                    if (isset($reasonCounts[$reason])) $reasonCounts[$reason]++;
                    $messages = [
                        'no_user'  => 'NIP/employee_number tidak ditemukan.',
                        'bad_date' => 'Format tanggal tidak valid.',
                        'bad_time' => 'Format jam tidak valid.',
                        'db_error' => 'Gagal menyimpan ke database.',
                    ];
                    $this->recordPreviewRow($batch->id, $idx+2, null, $data, false, $reason, $messages[$reason] ?? 'Gagal.');
                }
            }

            $batch->update([
                'total_rows'   => $total,
                'success_rows' => $success,
                'failed_rows'  => $failed,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Import gagal: '.$e->getMessage()])->withInput();
        }

        $detail = [];
        foreach ($reasonCounts as $k=>$v){ if($v>0) $detail[] = ucfirst(str_replace('_',' ', $k))."={$v}"; }
        $detailText = $detail ? (' Rinci: '.implode(', ', $detail).'.') : '';
        return redirect()->route('admin_rs.attendances.batches.show', $batch)
            ->with('status', "Import selesai: {$success} berhasil, {$failed} gagal.".$detailText);
    }
    // List batches
    public function index(Request $request): View
    {
        $items = Batch::with('importer:id,name')
            ->orderByDesc('imported_at')
            ->paginate(12);
        return view('admin_rs.attendances.batches.index', compact('items'));
    }

    // Show batch detail
    public function show(Batch $batch, Request $request): View
    {
        $rows = $batch->attendances()->with('user:id,name,employee_number')
            ->orderByDesc('attendance_date')
            ->paginate(20)
            ->withQueryString();
        $preview = $batch->rows()->orderBy('row_no')->paginate(15)->withQueryString();
        $previewFailed = $batch->rows()->where('success', false)->count();
        $previewSuccess = $batch->rows()->where('success', true)->count();
        return view('admin_rs.attendances.batches.show', [
            'batch' => $batch->load('importer:id,name'),
            'rows'  => $rows,
            'preview' => $preview,
            'previewFailed' => $previewFailed,
            'previewSuccess'=> $previewSuccess,
        ]);
    }

    /* ------------------------- Helpers ------------------------- */
    private function determinePeriodAndPrevious(array $map, array $rows): array
    {
        // Kumpulkan semua tanggal valid dari baris
        $dates = [];
        foreach ($rows as $row) {
            $val = isset($map['attendance_date']) && $map['attendance_date'] !== false ? ($row[$map['attendance_date']] ?? null) : null;
            $dt = $this->parseDate((string)$val);
            if ($dt) $dates[] = $dt;
        }
        if (!$dates) return [null, null, null];

        // Tentukan bulan-tahun dari tanggal minimum & maksimum
        usort($dates, fn($a,$b) => $a <=> $b);
        $min = $dates[0];
        $max = $dates[count($dates)-1];
        if ($min->format('Y-m') !== $max->format('Y-m')) {
            return [null, null, null];
        }

        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $min->format('Y-m-01'));
        $end   = (clone $start)->endOfMonth();

        // Cari/buat AssessmentPeriod untuk bulan tsb
        $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $name = $months[(int)$start->format('n')].' '.$start->format('Y');

        $period = \App\Models\AssessmentPeriod::firstOrCreate(
            ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            ['name' => $name, 'status' => \App\Models\AssessmentPeriod::STATUS_ACTIVE]
        );

        // Cari batch aktif (belum superseded) untuk periode ini
        $prev = Batch::query()
            ->where('assessment_period_id', $period->id)
            ->where('is_superseded', false)
            ->orderByDesc('imported_at')
            ->first();

        return [$period->id, $name, $prev];
    }
    private function mapHeader(array $header): ?array
    {
        // Normalize header: lowercase and collapse internal spaces
        $normalized = array_map(function ($h) {
            $h = strtolower((string)$h);
            $h = preg_replace('/\s+/',' ', trim($h));
            return $h;
        }, $header);

        // Helper to find first match among aliases
        $find = function(array $aliases) use ($normalized) {
            foreach ($aliases as $a) {
                $idx = array_search($a, $normalized, true);
                if ($idx !== false) return $idx;
            }
            return false;
        };

        $map = [
            'employee_number' => $find(['employee_number','nip','pin','nip/pin']),
            'attendance_date' => $find(['attendance_date','tanggal']),
            // Scan times preferred
            'check_in'        => $find(['check_in','scan masuk','scan masuk (hh:mm)','jam masuk']),
            'check_out'       => $find(['check_out','scan keluar','scan keluar (hh:mm)','jam keluar']),
            'status'          => $find(['status']),
            'late'            => $find(['datang terlambat','terlambat']),
            'note'            => $find(['keterangan']),
            'holiday_umum'    => $find(['libur umum','libur - umum']),
            'holiday_rutin'   => $find(['libur rutin','libur - rutin']),
            // Additional fields from PDF/Excel
            'shift_name'      => $find(['nama shift','shift','nama shift (text)']),
            'scheduled_in'    => $find(['jam masuk','jam masuk (jadwal)']),
            'scheduled_out'   => $find(['jam keluar','jam keluar (jadwal)']),
            'early_leave'     => $find(['pulang awal']),
            'work_duration'   => $find(['durasi kerja']),
            'break_duration'  => $find(['istirahat durasi','istirahat']),
            'extra_break'     => $find(['istirahat lebih']),
            'overtime_end'    => $find(['lembur akhir']),
            'overtime_shift'  => $find(['shift lembur']),
        ];

        if ($map['employee_number'] === false || $map['attendance_date'] === false) return null;
        return $map;
    }

    private function rowToAssoc(?array $map, array $row): ?array
    {
        if (!$map) return null;
        $get = fn($key) => isset($map[$key]) && $map[$key] !== false ? ($row[$map[$key]] ?? null) : null;
        return [
            'employee_number' => trim((string)$get('employee_number')),
            'attendance_date' => trim((string)$get('attendance_date')),
            'check_in'        => trim((string)$get('check_in')),
            'check_out'       => trim((string)$get('check_out')),
            'status'          => trim((string)$get('status')),
            'late'            => trim((string)$get('late')),
            'note'            => trim((string)$get('note')),
            'holiday_umum'    => trim((string)$get('holiday_umum')),
            'holiday_rutin'   => trim((string)$get('holiday_rutin')),
            // extras
            'shift_name'      => trim((string)$get('shift_name')),
            'scheduled_in'    => trim((string)$get('scheduled_in')),
            'scheduled_out'   => trim((string)$get('scheduled_out')),
            'early_leave'     => trim((string)$get('early_leave')),
            'work_duration'   => trim((string)$get('work_duration')),
            'break_duration'  => trim((string)$get('break_duration')),
            'extra_break'     => trim((string)$get('extra_break')),
            'overtime_end'    => trim((string)$get('overtime_end')),
            'overtime_shift'  => trim((string)$get('overtime_shift')),
        ];
    }

    private function importRow(int $batchId, array $data): array
    {
        $user = User::query()->where('employee_number', $data['employee_number'] ?? null)->first();
        if (!$user) return [false, 'no_user'];

        $date = $this->parseDate($data['attendance_date'] ?? '');
        if (!$date) return [false, 'bad_date'];

        // DB kolom check_in/check_out adalah DATETIME -> gabungkan tanggal + jam scan
        $in  = $this->combineDateTime($date, $data['check_in'] ?? '');
        $out = $this->combineDateTime($date, $data['check_out'] ?? '');
        if (($data['check_in'] ?? '') !== '' && !$in) return [false, 'bad_time'];
        if (($data['check_out'] ?? '') !== '' && !$out) return [false, 'bad_time'];

        $status = $this->deriveStatus($data, $in, $out);

        try {
            Attendance::updateOrCreate(
                [
                    'user_id'         => $user->id,
                    'attendance_date' => $date->format('Y-m-d'),
                ],
                [
                'user_id'          => $user->id,
                'attendance_date'  => $date->format('Y-m-d'),
                'check_in'         => $in,
                'check_out'        => $out,
                'shift_name'       => $data['shift_name'] ?: null,
                'scheduled_in'     => $this->ensureTime($data['scheduled_in'] ?? ''),
                'scheduled_out'    => $this->ensureTime($data['scheduled_out'] ?? ''),
                'late_minutes'         => $this->durationToMinutes($data['late'] ?? ''),
                'early_leave_minutes'  => $this->durationToMinutes($data['early_leave'] ?? ''),
                'work_duration_minutes'=> $this->durationToMinutes($data['work_duration'] ?? ''),
                'break_duration_minutes'=> $this->durationToMinutes($data['break_duration'] ?? ''),
                'extra_break_minutes'  => $this->durationToMinutes($data['extra_break'] ?? ''),
                'overtime_end'     => $this->ensureTime($data['overtime_end'] ?? ''),
                'holiday_public'   => $this->toBool($data['holiday_umum'] ?? ''),
                'holiday_regular'  => $this->toBool($data['holiday_rutin'] ?? ''),
                'overtime_shift'   => $this->toBool($data['overtime_shift'] ?? ''),
                'attendance_status'=> $status->value,
                'note'             => $data['note'] ?: null,
                'overtime_note'    => $this->deriveNote($data),
                'source'           => AttendanceSource::IMPORT->value,
                'import_batch_id'  => $batchId,
            ]);
            return [true, $user];
        } catch (\Throwable $e) {
            return [false, 'db_error'];
        }
    }
    private function combineDateTime(\DateTimeImmutable $date, ?string $time): ?string
    {
        $time = trim((string)$time);
        if ($time === '') return null;
        if (preg_match('/^0{1,2}:0{2}(:0{2})?$/', $time)) return null;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = $m[3] ?? '00';
            return $date->format('Y-m-d')." $hh:$mm:$ss";
        }
        return null;
    }

    private function parseDate(string $val): ?\DateTimeImmutable
    {
        $val = trim($val);
        if ($val === '') return null;
        // Strip leading day name like "Wednesday 01-01-2025" -> take last token that looks like a date
        if (preg_match('/(\d{2}[\/-]\d{2}[\/-]\d{4}|\d{4}[\/-]\d{2}[\/-]\d{2})/', $val, $m)) {
            $val = $m[1];
        }
        $formats = ['Y-m-d','d/m/Y','d-m-Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $val);
            if ($dt) return $dt;
        }
        if (is_numeric($val)) {
            $base = new \DateTimeImmutable('1899-12-30');
            $dt = $base->modify("+{$val} days");
            if ($dt) return $dt;
        }
        return null;
    }

    private function ensureTime(?string $time): ?string
    {
        $time = trim((string)$time);
        if ($time === '') return null;
        if (preg_match('/^0{1,2}:0{2}(:0{2})?$/', $time)) return null;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = $m[3] ?? '00';
            return "$hh:$mm:$ss";
        }
        return null;
    }

    private function durationToMinutes(?string $val): ?int
    {
        $val = trim((string)$val);
        if ($val === '') return null;
        if ($val === '0' || $val === '00:00' || $val === '0:00') return 0;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $val, $m)) {
            return ((int)$m[1]) * 60 + (int)$m[2];
        }
        if (is_numeric($val)) return (int)$val; // already minutes
        return null;
    }

    private function toBool(?string $v): bool
    {
        $v = strtolower(trim((string)$v));
        if ($v === '' || $v === '0' || $v === 'false' || $v === 'no') return false;
        return in_array($v, ['1','true','yes','y','ya','✓','check','x'], true) || $v !== '';
    }

    private function deriveStatus(array $data, ?string $checkIn, ?string $checkOut): AttendanceStatus
    {
        // Direct status if provided
        $s = strtolower(trim((string)($data['status'] ?? '')));
        if ($s !== '') {
            return match ($s) {
                'sakit'      => AttendanceStatus::SAKIT,
                'izin'       => AttendanceStatus::IZIN,
                'cuti'       => AttendanceStatus::CUTI,
                'terlambat'  => AttendanceStatus::TERLAMBAT,
                'absen'      => AttendanceStatus::ABSEN,
                default      => AttendanceStatus::HADIR,
            };
        }

        // From Indonesian sheet columns
        $late = strtolower(trim((string)($data['late'] ?? '')));
        $note = strtolower(trim((string)($data['note'] ?? '')));
        $liburUmum  = strtolower(trim((string)($data['holiday_umum'] ?? '')));
        $liburRutin = strtolower(trim((string)($data['holiday_rutin'] ?? '')));

        if (str_contains($note, 'sakit')) return AttendanceStatus::SAKIT;
        if (str_contains($note, 'izin'))  return AttendanceStatus::IZIN;
        if (str_contains($note, 'cuti'))  return AttendanceStatus::CUTI;

        // If both times empty
        if (!$checkIn && !$checkOut) {
            return AttendanceStatus::ABSEN;
        }

        if ($late !== '' && $late !== '00:00' && $late !== '0:00') {
            return AttendanceStatus::TERLAMBAT;
        }

        return AttendanceStatus::HADIR;
    }

    private function deriveNote(array $data): ?string
    {
        $bits = [];
        if (!empty($data['note'])) $bits[] = (string)$data['note'];
        $umum  = strtolower(trim((string)($data['holiday_umum'] ?? '')));
        $rutin = strtolower(trim((string)($data['holiday_rutin'] ?? '')));
        if ($umum !== '' && $umum !== '0') $bits[] = 'Libur Umum';
        if ($rutin !== '' && $rutin !== '0') $bits[] = 'Libur Rutin';
        return $bits ? implode('; ', $bits) : null;
    }

    /**
     * Baca file tabular (csv/xls/xlsx) dan kembalikan [header, rows]
     * rows merupakan array of array string sesuai urutan header
     */
    private function readTabularFile(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['csv','txt'])) {
            $handle = fopen($path, 'r');
            if ($handle === false) throw new \RuntimeException('Gagal membuka file.');
            $header = fgetcsv($handle);
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) { $rows[] = $row; }
            fclose($handle);
            return [$header, $rows];
        }

        // xls/xlsx via PhpSpreadsheet
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $header = array_shift($rows);
            return [$header, $rows];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal membaca file Excel: '.$e->getMessage());
        }
    }

    private function recordPreviewRow(int $batchId, int $rowNo, ?int $userId, ?array $assoc, bool $success, ?string $errCode, ?string $errMsg): void
    {
        try {
            \App\Models\AttendanceImportRow::create([
                'batch_id'      => $batchId,
                'row_no'        => $rowNo,
                'user_id'       => $userId,
                'employee_number'=> $assoc['employee_number'] ?? null,
                'raw_data'      => $assoc ? json_encode($assoc) : null,
                'success'       => $success,
                'error_code'    => $errCode,
                'error_message' => $errMsg,
            ]);
        } catch (\Throwable $e) {
            // ignore preview persistence errors
        }
    }
}
