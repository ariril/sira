<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillHolidayAttendanceStatus extends Command
{
    protected $signature = 'attendance:backfill-holiday-status
        {--batch-id= : Batasi ke attendance_import_batches.id tertentu}
        {--from= : Batasi tanggal mulai (YYYY-MM-DD)}
        {--to= : Batasi tanggal akhir (YYYY-MM-DD)}
        {--dry-run : Hanya tampilkan jumlah yang akan diubah, tanpa update}';

    protected $description = 'Backfill attendance_status menjadi Libur Umum/Libur Rutin untuk baris yang ditandai libur dan tidak memiliki scan masuk/keluar.';

    public function handle(): int
    {
        $batchId = (int) ($this->option('batch-id') ?: 0);
        $from = trim((string) ($this->option('from') ?: ''));
        $to = trim((string) ($this->option('to') ?: ''));
        $dryRun = (bool) $this->option('dry-run');

        $base = DB::table('attendances')
            ->whereNull('check_in')
            ->whereNull('check_out')
            ->whereIn('attendance_status', [
                AttendanceStatus::HADIR->value,
                AttendanceStatus::ABSEN->value,
            ])
            ->where(function ($q) {
                $q->where('holiday_public', 1)->orWhere('holiday_regular', 1);
            });

        if ($batchId > 0) {
            $base->where('import_batch_id', $batchId);
        }
        if ($from !== '') {
            $base->whereDate('attendance_date', '>=', $from);
        }
        if ($to !== '') {
            $base->whereDate('attendance_date', '<=', $to);
        }

        // Priority matches import logic: public holiday wins over regular holiday.
        $publicQ = (clone $base)->where('holiday_public', 1);
        $regularQ = (clone $base)->where('holiday_public', 0)->where('holiday_regular', 1);

        $cntPublic = (int) $publicQ->count();
        $cntRegular = (int) $regularQ->count();

        $this->info('attendance:backfill-holiday-status');
        $this->line('candidates:');
        $this->line('- Libur Umum  : ' . $cntPublic);
        $this->line('- Libur Rutin : ' . $cntRegular);

        if ($dryRun) {
            $this->warn('dry-run enabled: no updates performed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($publicQ, $regularQ, &$updatedPublic, &$updatedRegular): void {
            $now = now();
            $updatedPublic = (int) $publicQ->update([
                'attendance_status' => AttendanceStatus::LIBUR_UMUM->value,
                'updated_at' => $now,
            ]);
            $updatedRegular = (int) $regularQ->update([
                'attendance_status' => AttendanceStatus::LIBUR_RUTIN->value,
                'updated_at' => $now,
            ]);
        });

        $this->info('updated:');
        $this->line('- Libur Umum  : ' . (int) ($updatedPublic ?? 0));
        $this->line('- Libur Rutin : ' . (int) ($updatedRegular ?? 0));

        return self::SUCCESS;
    }
}
