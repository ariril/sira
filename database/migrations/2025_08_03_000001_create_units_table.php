<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nama_unit
            $table->string('slug')->unique(); // ex: 'poliklinik-bedah', 'igd'
            $table->string('code', 20)->nullable();
            $table->enum('type', [ // isi tetap bahasa Indonesia
                'manajemen', 'admin_rs', 'penunjang', // keuangan, SDM, IT, dll
                'rawat_inap', 'igd', 'poliklinik', // klinis
                'lainnya'
            ])->default('poliklinik');

            $table->foreignId('parent_id')->nullable()
                ->constrained('units')->nullOnDelete(); 

            $table->string('location')->nullable(); // lokasi gedung/lantai
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // // proporsi remunerasi per unit
            // $table->decimal('remuneration_ratio', 5, 2)->default(0.00);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
