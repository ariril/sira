<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_kinerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('periode_penilaian_id')->constrained('periode_penilaians')->onDelete('cascade');
            $table->date('tanggal_penilaian');
            $table->decimal('skor_total_wsm', 8, 2)->nullable();
            $table->string('status_validasi', 50); // Menunggu Validasi, Tervalidasi, Ditolak
            $table->text('komentar_atasan')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'periode_penilaian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_kinerjas');
    }
};
