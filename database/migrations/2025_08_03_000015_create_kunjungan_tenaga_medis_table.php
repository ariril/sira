<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_tenaga_medis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungans')->cascadeOnDelete();
            $table->foreignId('tenaga_medis_id')->constrained('users')->cascadeOnDelete();
            $table->enum('peran', ['dokter','perawat','lab','farmasi','admin','lainnya']);
            $table->unsignedInteger('durasi_menit')->nullable();
            $table->timestamps();

            $table->unique(['kunjungan_id','tenaga_medis_id','peran'], 'uniq_kunjungan_petugas');
            $table->index(['tenaga_medis_id','created_at'], 'idx_petugas_waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_tenaga_medis');
    }
};
