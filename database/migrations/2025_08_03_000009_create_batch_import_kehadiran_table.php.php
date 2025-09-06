<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_import_kehadiran', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');                           // mis. simrs_khanza_2025-09-03.xlsx
            $table->foreignId('diimpor_oleh')->nullable()           // user yang menjalankan impor (boleh null untuk job sistem)
            ->constrained('users')->nullOnDelete();
            $table->timestamp('diimpor_pada')->nullable();
            $table->unsignedInteger('total_baris')->nullable();
            $table->unsignedInteger('baris_berhasil')->nullable();
            $table->unsignedInteger('baris_gagal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kehadiran_import_batches');
    }
};
