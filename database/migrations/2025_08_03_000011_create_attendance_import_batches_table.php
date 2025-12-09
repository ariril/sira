<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_import_batches', function (Blueprint $table) {
            $table->id();

            $table->string('file_name'); // nama_file, ex: simrs_khanza_2025-09-03.xlsx

            $table->foreignId('assessment_period_id')->nullable()
                ->constrained('assessment_periods')->nullOnDelete();
                
            $table->foreignId('imported_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('imported_at')->nullable();

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('success_rows')->nullable();
            $table->unsignedInteger('failed_rows')->nullable();
            
            $table->boolean('is_superseded')->default(false);
            $table->foreignId('previous_batch_id')->nullable()
                    ->constrained('attendance_import_batches')->nullOnDelete();

            $table->timestamps();

            // Replace broad unique (assessment_period_id, is_superseded) with generated column
            // active_period_key ensures only one active (non-superseded) batch per period.
            $table->unsignedBigInteger('active_period_key')->nullable()
                ->storedAs('(case when (is_superseded = 0) then assessment_period_id else null end)');
            $table->unique(['active_period_key'], 'uniq_active_period_key');
            $table->index('assessment_period_id', 'idx_att_batch_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_batches');
    }
};
