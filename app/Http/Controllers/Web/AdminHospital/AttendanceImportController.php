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

class AttendanceImportController extends Controller
{
    // Upload form
    public function create(Request $request): View
    {
        return view('admin_rs.attendances.import.create');
    }

    // Handle upload + import CSV
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required','file','mimetypes:text/plain,text/csv,application/csv','max:5120'],
        ]);

        $file = $validated['file'];

        $storedPath = $file->store('attendance_imports');
        $original   = $file->getClientOriginalName();

        $batch = null;
        $success = 0; $failed = 0; $total = 0;

        DB::beginTransaction();
        try {
            $batch = Batch::create([
                'file_name'   => $original,
                'imported_by' => Auth::id(),
                'imported_at' => now(),
                'total_rows'  => 0,
                'success_rows'=> 0,
                'failed_rows' => 0,
            ]);

            $handle = fopen(Storage::path($storedPath), 'r');
            if ($handle === false) {
                throw new \RuntimeException('Gagal membuka file unggahan.');
            }

            // Header: employee_number,attendance_date,check_in,check_out,status
            $header = fgetcsv($handle);
            if (!$header) {
                throw new \RuntimeException('File kosong atau header tidak ditemukan.');
            }
            $map = $this->mapCsvHeader($header);
            if (!$map) {
                throw new \RuntimeException('Header tidak sesuai. Gunakan: employee_number, attendance_date, check_in, check_out, status');
            }

            while (($row = fgetcsv($handle)) !== false) {
                $total++;
                $data = $this->rowToAssoc($map, $row);
                if (!$data) { $failed++; continue; }

                $ok = $this->importRow($batch->id, $data);
                if ($ok) $success++; else $failed++;
            }
            fclose($handle);

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

        return redirect()->route('admin_rs.attendances.batches.show', $batch)
            ->with('status', "Import selesai: {$success} berhasil, {$failed} gagal.");
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
        return view('admin_rs.attendances.batches.show', [
            'batch' => $batch->load('importer:id,name'),
            'rows'  => $rows,
        ]);
    }

    /* ------------------------- Helpers ------------------------- */
    private function mapCsvHeader(array $header): ?array
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
            // Prefer scan times if available, else jam
            'check_in'        => $find(['check_in','scan masuk','jam masuk']),
            'check_out'       => $find(['check_out','scan keluar','jam keluar']),
            'status'          => $find(['status']),
            'late'            => $find(['datang terlambat','terlambat']),
            'note'            => $find(['keterangan']),
            'holiday_umum'    => $find(['libur umum','libur - umum']),
            'holiday_rutin'   => $find(['libur rutin','libur - rutin']),
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
        ];
    }

    private function importRow(int $batchId, array $data): bool
    {
        $user = User::query()->where('employee_number', $data['employee_number'] ?? null)->first();
        if (!$user) return false;

        $date = $this->parseDate($data['attendance_date'] ?? '');
        if (!$date) return false;

        $in  = $this->combineDateTime($date, $data['check_in'] ?? '');
        $out = $this->combineDateTime($date, $data['check_out'] ?? '');

        $status = $this->deriveStatus($data, $in, $out);

        Attendance::create([
            'user_id'          => $user->id,
            'attendance_date'  => $date->format('Y-m-d'),
            'check_in'         => $in,
            'check_out'        => $out,
            'attendance_status'=> $status->value,
            'overtime_note'    => $this->deriveNote($data),
            'source'           => AttendanceSource::IMPORT->value,
            'import_batch_id'  => $batchId,
        ]);
        return true;
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

    private function combineDateTime(\DateTimeImmutable $date, ?string $time): ?string
    {
        $time = trim((string)$time);
        if ($time === '') return null;
        // Treat 00:00 or 0:00 as empty
        if (preg_match('/^0{1,2}:0{2}(:0{2})?$/', $time)) return null;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time)) {
            return $date->format('Y-m-d')." ".$time;
        }
        return null;
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
}
