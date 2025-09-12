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

            $table->date('attendance_date'); // tanggal_hadir
            $table->time('check_in')->nullable();  // jam_masuk
            $table->time('check_out')->nullable(); // jam_keluar

            $table->enum('attendance_status', [    // status_kehadiran
                'Hadir',
                'Sakit',
                'Izin',
                'Cuti',
                'Terlambat',
                'Absen',
            ])->default('Hadir');

            $table->text('overtime_note')->nullable(); // catatan_lembur

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
