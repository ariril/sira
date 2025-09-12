<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_import_batches', function (Blueprint $table) {
            $table->id();

            $table->string('file_name'); // nama_file, ex: simrs_khanza_2025-09-03.xlsx

            $table->foreignId('imported_by')->nullable()
            ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('imported_at')->nullable();

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('success_rows')->nullable();
            $table->unsignedInteger('failed_rows')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_batches');
    }
};
