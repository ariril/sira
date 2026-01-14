<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('metric_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();

            $table->foreignId('imported_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');

            $table->boolean('is_superseded')->default(false);
            $table->foreignId('previous_batch_id')
                ->nullable()
                ->constrained('metric_import_batches')
                ->nullOnDelete();

            $table->unsignedBigInteger('active_period_key')->nullable()
                ->storedAs('(case when (is_superseded = 0) then assessment_period_id else null end)');

            $table->timestamps();

            $table->index(['assessment_period_id', 'status'], 'idx_metric_batch_period_status');
            $table->unique(['active_period_key'], 'uniq_metric_active_period_key');
        });

        Schema::create('imported_criteria_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_batch_id')
                ->constrained('metric_import_batches')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();

            $table->foreignId('performance_criteria_id')
                ->constrained('performance_criterias')
                ->cascadeOnDelete();

            $table->decimal('value_numeric', 15, 4)->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->text('value_text')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_batch_id')
                ->nullable()
                ->constrained('metric_import_batches')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id','assessment_period_id','performance_criteria_id','is_active'], 'uniq_imported_value_active');
            $table->index(['assessment_period_id','is_active'], 'idx_imported_values_period_active');
            $table->index(['performance_criteria_id'], 'idx_imported_values_criteria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_criteria_values');
        Schema::dropIfExists('metric_import_batches');
    }
};
