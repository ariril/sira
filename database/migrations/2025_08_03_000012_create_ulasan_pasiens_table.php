<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ulasan_pasiens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->nullable()->constrained('pasiens')->onDelete('set null');
            $table->foreignId('pegawai_medis_id')->nullable()->constrained('pegawais')->onDelete('set null');
            $table->timestamp('tanggal_ulasan')->useCurrent();
            $table->integer('rating_layanan')->nullable();
            $table->text('komentar_saran_kritik')->nullable();
            $table->string('tipe_feedback', 50); // Saran, Kritik, Kepuasan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ulasan_pasiens');
    }
};
