<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrian_pasiens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasiens')->onDelete('cascade');
            $table->date('tanggal_antri');
            $table->integer('nomor_antrian');
            $table->string('status_antrian', 50); // Menunggu, Sedang Dilayani, Selesai, Batal
            $table->time('waktu_masuk_antrian')->nullable();
            $table->time('waktu_mulai_dilayani')->nullable();
            $table->time('waktu_selesai_dilayani')->nullable();
            $table->foreignId('dokter_bertugas_id')->nullable()->constrained('users')->onDelete('set null'); // Dokter yang melayani
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antrian_pasiens');
    }
};
