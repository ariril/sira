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
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Services\Imports\TabularFileReader;
use App\Services\Imports\EmployeeNumberNormalizer;
use App\Services\Attendances\Imports\AttendanceImportRowMapper;
use App\Services\Attendances\Imports\AttendanceImportTemplateBuilder;
use App\Models\AssessmentPeriod;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Support\AssessmentPeriodGuard;

class AttendanceImportController extends Controller
{
    public function __construct(
        private readonly TabularFileReader $tabularFileReader,
        private readonly EmployeeNumberNormalizer $employeeNumberNormalizer,
        private readonly AttendanceImportRowMapper $attendanceImportRowMapper,
        private readonly AttendanceImportTemplateBuilder $attendanceImportTemplateBuilder,
    ) {
    }

    // Upload form
    public function create(Request $request): View
    {
        $latestLockedPeriod = AssessmentPeriodGuard::resolveLatestLocked();
        $activePeriod = AssessmentPeriodGuard::resolveActive();

        return view('admin_rs.attendances.import.create', compact('latestLockedPeriod', 'activePeriod'));
    }

    // Preview 5 first rows (for client-side warning before import)
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $validated['file'];

        try {
            [$header, $rows] = $this->readTabularFile($file->getRealPath(), $file->getClientOriginalExtension());
            if (!$header) {
                return response()->json([
                    'ok' => false,
                    'message' => 'File kosong atau header tidak ditemukan.',
                ], 422);
            }

            $map = $this->mapHeader($header);
            if (!$map) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Header tidak sesuai. Pastikan terdapat kolom NIP dan Tanggal.',
                ], 422);
            }

            // Tentukan periode dari isi file, dan pastikan periode tersebut sudah LOCKED.
            [$periodId, $periodText, $prevActive] = $this->determinePeriodAndPrevious($map, $rows);
            if ($periodId === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Rentang tanggal pada file mencakup lebih dari satu bulan. Pisahkan per bulan.',
                ], 422);
            }

            $period = AssessmentPeriodGuard::resolveById((int) $periodId);
            if (!$period || $period->status !== AssessmentPeriod::STATUS_LOCKED) {
                $status = $period ? strtoupper((string) $period->status) : '-';
                return response()->json([
                    'ok' => false,
                    'message' => "Import absensi hanya dapat dilakukan untuk periode LOCKED. Periode file: {$periodText} (status: {$status}).",
                ], 422);
            }

            $previewRows = [];
            $warnScientific = false;
            $warnNumeric = false;

            foreach (array_slice($rows, 0, 5) as $idx => $row) {
                $assoc = $this->rowToAssoc($map, $row);

                $rawNip = (string)($assoc['employee_number_raw'] ?? ($assoc['employee_number'] ?? ''));
                $normalizedNip = (string)($assoc['employee_number'] ?? '');

                if ($this->looksLikeScientificNotation($rawNip)) {
                    $warnScientific = true;
                }
                // NOTE: Do not warn just because NIP is long digits.
                // Long NIP is normal; we only warn when it actually looks like scientific (E+) formatting.

                $previewRows[] = [
                    'row_no' => $idx + 2,
                    'nip' => $rawNip,
                    'nip_normalized' => $normalizedNip,
                    'nama' => (string)($assoc['employee_name'] ?? ''),
                    'tanggal' => (string)($assoc['attendance_date'] ?? ''),
                    'scan_masuk' => (string)($assoc['check_in'] ?? ''),
                    'scan_keluar' => (string)($assoc['check_out'] ?? ''),
                ];
            }

            return response()->json([
                'ok' => true,
                'preview' => $previewRows,
                'warnings' => [
                    'nip_scientific' => $warnScientific,
                    'nip_numeric_long' => $warnNumeric,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal membaca file untuk preview: ' . $e->getMessage(),
            ], 422);
        }
    }

    // Download simple Excel template (ID header)
    public function template(Request $request)
    {
        try {
            $built = $this->attendanceImportTemplateBuilder->build();
            return response()->download($built['tmpPath'], $built['fileName'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            // Fallback CSV if something goes wrong
            $headers = [
                'PIN', 'NIP', 'Nama', 'Jabatan', 'Ruangan', 'Periode Mulai', 'Periode Selesai', 'Tanggal', 'Nama Shift',
                'Jam Masuk', 'Scan Masuk', 'Datang Terlambat', 'Jam Keluar', 'Scan Keluar', 'Pulang Awal',
                'Durasi Kerja', 'Istirahat Durasi', 'Istirahat Lebih', 'Lembur Akhir', 'Libur Umum', 'Libur Rutin',
                'Shift Lembur', 'Keterangan',
            ];

            $fallback = $this->attendanceImportTemplateBuilder->buildCsvFallback($headers);
            $fileName = $fallback['fileName'];
            $csv = $fallback['csv'];

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        }
    }

    // Handle upload + import (CSV/XLS/XLSX)
    public function store(Request $request, PeriodPerformanceAssessmentService $perfSvc): RedirectResponse
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
        $importPeriodId = null;
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
            $importPeriodId = (int) $periodId;

            $period = AssessmentPeriodGuard::resolveById((int) $periodId);
            if (!$period || $period->status !== AssessmentPeriod::STATUS_LOCKED) {
                $status = $period ? strtoupper((string) $period->status) : '-';
                throw new \RuntimeException("Import absensi hanya dapat dilakukan untuk periode LOCKED. Periode file: {$periodText} (status: {$status}).");
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
                [$ok, $reasonOrUser, $parsed] = $this->importRow($batch->id, $data);
                if ($ok) {
                    $success++;
                    $this->recordPreviewRow($batch->id, $idx+2, $reasonOrUser?->id, $data, true, null, null, $parsed);
                } else {
                    $failed++;
                    $reason = $reasonOrUser;
                    if (isset($reasonCounts[$reason])) $reasonCounts[$reason]++;
                    $this->recordPreviewRow(
                        $batch->id,
                        $idx + 2,
                        null,
                        $data,
                        false,
                        $reason,
                        $this->buildRowErrorMessage($reason, $data),
                        $parsed
                    );
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

        // Update Penilaian Saya after import (scores depend on attendance-derived criteria).
        if ($importPeriodId) {
            $perfSvc->recalculateForPeriodId($importPeriodId);
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
        $rowsPageName = 'rows_page';
        $perPageOptions = [12, 25, 50, 100];
        $perPage = (int) $request->input('per_page', 12);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 12;
        }

        $q = trim((string)$request->input('q', ''));
        $result = $request->input('result', 'all');

        $rowsQuery = $batch->rows()
            ->with('user:id,name,employee_number');

        if ($q !== '') {
            $rowsQuery->where(function ($query) use ($q) {
                $query->whereHas('user', function ($uq) use ($q) {
                    $uq->where('name', 'like', "%{$q}%")
                        ->orWhere('employee_number', 'like', "%{$q}%");
                })->orWhere('employee_number', 'like', "%{$q}%");
            });
        }

        if ($result === 'success') {
            $rowsQuery->where('success', true);
        } elseif ($result === 'failed') {
            $rowsQuery->where('success', false);
        }

        $rows = $rowsQuery
            ->orderBy('row_no')
            ->paginate($perPage, ['*'], $rowsPageName)
            ->appends($request->except($rowsPageName));

        $attendanceMap = $batch->attendances()
            ->get()
            ->keyBy(fn($att) => $att->user_id.'|'.($att->attendance_date
                ? \Illuminate\Support\Carbon::parse($att->attendance_date)->format('Y-m-d')
                : ''));

        return view('admin_rs.attendances.batches.show', [
            'batch' => $batch->load('importer:id,name'),
            'rows'  => $rows,
            'rowsPageName' => $rowsPageName,
            'attendanceMap' => $attendanceMap,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'result' => $result,
            'q' => $q,
        ]);
    }

    /* ------------------------- Helpers ------------------------- */
    private function determinePeriodAndPrevious(array $map, array $rows): array
    {
        // Kumpulkan semua tanggal valid dari baris
        $dates = [];
        foreach ($rows as $row) {
            $val = isset($map['attendance_date']) && $map['attendance_date'] !== false ? ($row[$map['attendance_date']] ?? null) : null;
            $dt = $this->parseDate($val);
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
        return $this->attendanceImportRowMapper->mapHeader($header);
    }

    private function rowToAssoc(?array $map, array $row): ?array
    {
        return $this->attendanceImportRowMapper->rowToAssoc($map, $row);
    }

    private function importRow(int $batchId, array $data): array
    {
        $user = User::query()->where('employee_number', $data['employee_number'] ?? null)->first();
        $parsed = [
            'attendance_date' => null,
            'check_in' => null,
            'check_out' => null,
        ];

        if (!$user) return [false, 'no_user', $parsed];

        $date = $this->parseDate($data['attendance_date'] ?? '');
        if ($date) {
            $parsed['attendance_date'] = $date->format('Y-m-d');
        }
        if (!$date) return [false, 'bad_date', $parsed];

        // DB kolom check_in/check_out adalah DATETIME -> gabungkan tanggal + jam scan
        $rawIn = $data['check_in'] ?? '';
        $rawOut = $data['check_out'] ?? '';

        $in  = $this->combineDateTime($date, $rawIn);
        $out = $this->combineDateTime($date, $rawOut);
        if ($in) $parsed['check_in'] = 
            \Carbon\Carbon::parse($in)->format('H:i');
        if ($out) $parsed['check_out'] = 
            \Carbon\Carbon::parse($out)->format('H:i');

        // Treat 00:00 as empty time (often used for libur/blank rows)
        if ($this->hasNonEmptyNonZeroTime($rawIn) && !$in) return [false, 'bad_time', $parsed];
        if ($this->hasNonEmptyNonZeroTime($rawOut) && !$out) return [false, 'bad_time', $parsed];

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
            return [true, $user, $parsed];
        } catch (\Throwable $e) {
            return [false, 'db_error', $parsed];
        }
    }
    private function combineDateTime(\DateTimeImmutable $date, mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        // Excel time serial (fraction of day). 0 means 00:00.
        if (is_int($time) || is_float($time) || (is_string($time) && preg_match('/^\d+(?:\.\d+)?$/', trim($time)))) {
            $num = (float) trim((string) $time);
            if ($num == 0.0) {
                return null;
            }
            try {
                $dt = ExcelDate::excelToDateTimeObject($num);
                return $date->format('Y-m-d') . ' ' . $dt->format('H:i:s');
            } catch (\Throwable $e) {
                // fall through to string parsing
            }
        }

        $time = trim((string)$time);
        if ($time === '') return null;
        if ($this->isZeroTimeString($time)) return null;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = $m[3] ?? '00';
            return $date->format('Y-m-d')." $hh:$mm:$ss";
        }
        return null;
    }

    private function parseDate(mixed $val): ?\DateTimeImmutable
    {
        if ($val === null) return null;

        // Excel serial date (number)
        if (is_int($val) || is_float($val) || (is_string($val) && preg_match('/^\d+(?:\.\d+)?$/', trim($val)))) {
            $num = (float)trim((string)$val);
            try {
                $dt = ExcelDate::excelToDateTimeObject($num);
                $dt->setTime(0, 0, 0);
                return \DateTimeImmutable::createFromMutable($dt);
            } catch (\Throwable $e) {
                // fall through to string parsing
            }
        }

        $str = trim((string)$val);
        if ($str === '') return null;

        // If string contains day name like "Monday 01-12-2025" take the date portion
        if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}[\/-]\d{1,2}[\/-]\d{1,2})/', $str, $m)) {
            $str = $m[1];
        }

        // Normalize separators and pad day/month
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $str, $m)) {
            $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $y = $m[3];
            $str = "$d-$mo-$y";
        }

        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $str);
            if ($dt) return $dt;
        }

        return null;
    }

    private function cellToString(mixed $val): string
    {
        return $this->attendanceImportRowMapper->cellToString($val);
    }

    private function looksLikeScientificNotation(string $val): bool
    {
        return $this->employeeNumberNormalizer->looksLikeScientificNotation($val);
    }

    private function looksLikeLongNumericIdentifier(string $val): bool
    {
        return $this->attendanceImportRowMapper->looksLikeLongNumericIdentifier($val);
    }

    private function normalizeEmployeeNumber(mixed $val): string
    {
        return $this->employeeNumberNormalizer->normalize($this->cellToString($val));
    }

    /**
     * Expand strings like "1.97909012020803E+16" into "19790901202080300".
     * Returns null if format is not supported.
     */
    private function expandScientificToPlainInteger(string $val): ?string
    {
        return $this->employeeNumberNormalizer->expandScientificToPlainInteger($val);
    }

    private function ensureTime(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        // Excel time serial (fraction of day). 0 means 00:00.
        if (is_int($time) || is_float($time) || (is_string($time) && preg_match('/^\d+(?:\.\d+)?$/', trim($time)))) {
            $num = (float) trim((string) $time);
            if ($num == 0.0) {
                return null;
            }
            try {
                $dt = ExcelDate::excelToDateTimeObject($num);
                return $dt->format('H:i:s');
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $time = trim((string)$time);
        if ($time === '') return null;
        if ($this->isZeroTimeString($time)) return null;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = $m[3] ?? '00';
            return "$hh:$mm:$ss";
        }
        return null;
    }

    private function isZeroTimeString(string $time): bool
    {
        $time = trim($time);
        return (bool) preg_match('/^0{1,2}:0{2}(:0{2})?$/', $time);
    }

    private function hasNonEmptyNonZeroTime(mixed $time): bool
    {
        if ($time === null) {
            return false;
        }
        $s = trim((string) $time);
        if ($s === '') {
            return false;
        }
        if ($this->isZeroTimeString($s)) {
            return false;
        }
        // If numeric string/number and equals 0 -> treat empty
        if (is_numeric($s) && ((float) $s) == 0.0) {
            return false;
        }
        return true;
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
                'libur umum'  => AttendanceStatus::LIBUR_UMUM,
                'libur rutin' => AttendanceStatus::LIBUR_RUTIN,
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
        $holidayPublic = $this->toBool($data['holiday_umum'] ?? '');
        $holidayRegular = $this->toBool($data['holiday_rutin'] ?? '');

        if (str_contains($note, 'sakit')) return AttendanceStatus::SAKIT;
        if (str_contains($note, 'izin'))  return AttendanceStatus::IZIN;
        if (str_contains($note, 'cuti'))  return AttendanceStatus::CUTI;

        // If both times empty
        if (!$checkIn && !$checkOut) {
            if ($holidayPublic) {
                return AttendanceStatus::LIBUR_UMUM;
            }
            if ($holidayRegular) {
                return AttendanceStatus::LIBUR_RUTIN;
            }
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
        return $this->tabularFileReader->read($path, $ext);
    }

    private function recordPreviewRow(int $batchId, int $rowNo, ?int $userId, ?array $assoc, bool $success, ?string $errCode, ?string $errMsg, ?array $parsed = null): void
    {
        try {
            \App\Models\AttendanceImportRow::create([
                'batch_id'      => $batchId,
                'row_no'        => $rowNo,
                'user_id'       => $userId,
                'employee_number'=> $assoc['employee_number'] ?? null,
                'raw_data'      => $assoc,
                'parsed_data'   => $parsed,
                'success'       => $success,
                'error_code'    => $errCode,
                'error_message' => $errMsg,
            ]);
        } catch (\Throwable $e) {
            // ignore preview persistence errors
        }
    }

    private function buildRowErrorMessage(string $reason, array $data): string
    {
        $default = [
            'no_user'  => 'NIP/employee_number tidak ditemukan.',
            'bad_date' => 'Format tanggal tidak valid.',
            'bad_time' => 'Format jam tidak valid.',
            'db_error' => 'Gagal menyimpan ke database.',
        ];

        if ($reason !== 'no_user') {
            return $default[$reason] ?? 'Gagal.';
        }

        $raw = trim((string)($data['employee_number_raw'] ?? ($data['employee_number'] ?? '')));
        if ($raw !== '' && $this->looksLikeScientificNotation($raw)) {
            return 'NIP tidak ditemukan. NIP terdeteksi format scientific (E+). Ubah format kolom NIP menjadi TEXT di Excel lalu simpan ulang agar tidak gagal.';
        }

        return $default['no_user'];
    }
}
