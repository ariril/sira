<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_penilaians', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode');
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->string('siklus_penilaian', 20); // Bulanan, Triwulanan, Tahunan, dll.
            $table->string('status_periode', 20); // Aktif, Selesai, Draft, dll.
            $table->boolean('is_active')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_penilaians');
    }
};
