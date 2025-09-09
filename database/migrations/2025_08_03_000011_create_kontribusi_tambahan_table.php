<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontribusi_tambahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('judul_kontribusi');
            $table->text('deskripsi_kontribusi')->nullable();
            $table->date('tanggal_pengajuan');
            $table->string('file_bukti')->nullable(); // Path atau URL ke dokumen
            $table->string('status_validasi', 50); // Menunggu Persetujuan, Disetujui, Ditolak
            $table->text('komentar_supervisor')->nullable();
            $table->timestamps();$table->foreignId('periode_penilaian_id')->nullable()
                ->constrained('periode_penilaian')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontribusi_tambahan');
    }
};
