<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kehadiran_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');                           // mis. simrs_khanza_2025-09-03.xlsx
            $table->foreignId('imported_by')->nullable()           // user yang menjalankan impor (boleh null untuk job sistem)
            ->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('success_rows')->nullable();
            $table->unsignedInteger('failed_rows')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadiran_import_batches');
    }
};
