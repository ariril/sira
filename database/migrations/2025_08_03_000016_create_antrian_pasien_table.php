<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrian_pasien', function (Blueprint $table) {
            $table->id();

            // data antrean
            $table->date('tanggal_antri');
            $table->unsignedInteger('nomor_antrian');
            $table->string('status_antrian', 50)->default('Menunggu'); // Menunggu, Sedang Dilayani, Selesai, Batal
            $table->time('waktu_masuk_antrian')->nullable();
            $table->time('waktu_mulai_dilayani')->nullable();
            $table->time('waktu_selesai_dilayani')->nullable();

            // dokter yang bertugas (opsional)
            $table->foreignId('dokter_bertugas_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // >>> penambahan mulai di sini
            // unit kerja poli yang melayani
            $table->foreignId('unit_kerja_id')
                ->constrained('unit_kerja') // default: NOT NULL (bagus utk UNIQUE)
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // referensi pasien eksternal (no_rm/token), tanpa tabel pasien
            $table->string('patient_ref', 50)->nullable();

            // keterkaitan ke kunjungan (jika sudah dibuatkan tiket/visit)
            $table->foreignId('kunjungan_id')
                ->nullable()
                ->constrained('kunjungan')
                ->nullOnDelete();

            $table->timestamps();

            // indeks bantu untuk pencarian cepat
            $table->unique(['tanggal_antri','unit_kerja_id','nomor_antrian'], 'uniq_antri_per_unit_tanggal');
            $table->index('patient_ref', 'idx_antrian_patient_ref');
            $table->index(['unit_kerja_id','tanggal_antri','status_antrian','nomor_antrian'], 'idx_unit_tanggal_status_nomor');


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrian_pasien');
    }

};
