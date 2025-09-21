<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // nama_periode
            $table->date('start_date');              // tanggal_mulai
            $table->date('end_date');                // tanggal_akhir
            $table->string('cycle', 20);             // siklus_penilaian (Bulanan, Triwulanan, Tahunan, dll)
            $table->string('status', 20);            // status_periode (Aktif, Selesai, Draft, dll)
            $table->boolean('is_active')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_periods');
    }

};
