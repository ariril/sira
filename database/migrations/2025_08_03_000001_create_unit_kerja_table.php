<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit');
            $table->string('slug')->unique(); // ex: 'poliklinik-bedah', 'igd'
            $table->string('kode', 20)->nullable();
            $table->enum('type', [ // tipe unit
                'manajemen', 'administrasi', 'penunjang', // keuangan, SDM, IT, dll
                'rawat_inap', 'igd', 'poliklinik', // klinis
                'lainnya'
            ])->default('poliklinik');
            $table->foreignId('parent_id')->nullable()       // untuk hierarki (sub-unit)
            ->constrained('unit_kerja')->nullOnDelete();

            $table->string('lokasi')->nullable();            // gedung/lantai
            $table->string('telepon', 30)->nullable();
            $table->string('email', 150)->nullable();

            // untuk remunerasi per unit (yang sudah ada)
            $table->decimal('proporsi_remunerasi_unit', 5, 2)->default(0.00);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};
