<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Tanggal hadir (wajib)
            $table->date('attendance_date');

            // Waktu scan masuk/keluar dari mesin (disimpan sebagai datetime agar konsisten dengan model & validasi)
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();

            // Informasi tambahan dari sheet Excel (opsional)
            $table->string('shift_name', 100)->nullable();          // Nama Shift
            $table->time('scheduled_in')->nullable();               // Jam Masuk (jadwal)
            $table->time('scheduled_out')->nullable();              // Jam Keluar (jadwal)

            $table->unsignedSmallInteger('late_minutes')->nullable();        // Datang Terlambat (menit)
            $table->unsignedSmallInteger('early_leave_minutes')->nullable(); // Pulang Awal (menit)
            $table->unsignedSmallInteger('work_duration_minutes')->nullable();   // Durasi Kerja (menit)
            $table->unsignedSmallInteger('break_duration_minutes')->nullable();  // Istirahat Durasi (menit)
            $table->unsignedSmallInteger('extra_break_minutes')->nullable();     // Istirahat Lebih (menit)
            $table->time('overtime_end')->nullable();                 // Lembur Akhir (jam)
            $table->boolean('holiday_public')->default(false);        // Libur Umum
            $table->boolean('holiday_regular')->default(false);       // Libur Rutin
            $table->boolean('overtime_shift')->default(false);        // Shift Lembur

            $table->enum('attendance_status', [    // status_kehadiran
                'Hadir',
                'Sakit',
                'Izin',
                'Cuti',
                'Terlambat',
                'Absen',
            ])->default('Hadir');

            // Keterangan umum dari sheet + catatan lembur terpisah
            $table->text('note')->nullable();
            $table->text('overtime_note')->nullable();

            $table->enum('source', ['manual','import','integrasi'])
                ->default('import');

            $table->foreignId('import_batch_id')->nullable()
                ->constrained('attendance_import_batches')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'attendance_date'], 'uniq_user_date');
            $table->index(['attendance_date', 'attendance_status'], 'idx_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }

};
