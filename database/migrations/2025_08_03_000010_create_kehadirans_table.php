<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kehadirans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal_hadir');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->string('status_kehadiran', 50); // Hadir, Sakit, Izin, Cuti, Terlambat, Absen
            $table->text('catatan_lembur')->nullable();

            // >>> tambahan baru
            $table->enum('source', ['manual','import','integrasi'])->default('import');
            $table->foreignId('import_batch_id')->nullable()
                ->constrained('kehadiran_import_batches')->nullOnDelete();

            $table->timestamps();

            // Hindari duplikasi baris kehadiran per orang per hari
            $table->unique(['user_id', 'tanggal_hadir'], 'uniq_user_tanggal');
            $table->index(['tanggal_hadir', 'status_kehadiran'], 'idx_tanggal_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kehadirans');
    }
};
