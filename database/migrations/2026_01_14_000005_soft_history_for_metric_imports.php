<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('metric_import_batches')) {
            Schema::table('metric_import_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('metric_import_batches', 'is_superseded')) {
                    $table->boolean('is_superseded')->default(false)->after('status');
                }
                if (!Schema::hasColumn('metric_import_batches', 'previous_batch_id')) {
                    $table->foreignId('previous_batch_id')->nullable()->after('is_superseded')
                        ->constrained('metric_import_batches')->nullOnDelete();
                }

                if (!Schema::hasColumn('metric_import_batches', 'active_period_key')) {
                    $table->unsignedBigInteger('active_period_key')->nullable()
                        ->storedAs('(case when (is_superseded = 0) then assessment_period_id else null end)');
                    $table->unique(['active_period_key'], 'uniq_metric_active_period_key');
                }
            });
        }

        if (Schema::hasTable('imported_criteria_values')) {
            Schema::table('imported_criteria_values', function (Blueprint $table) {
                if (!Schema::hasColumn('imported_criteria_values', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('value_text');
                }
                if (!Schema::hasColumn('imported_criteria_values', 'superseded_at')) {
                    $table->timestamp('superseded_at')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('imported_criteria_values', 'superseded_by_batch_id')) {
                    $table->foreignId('superseded_by_batch_id')->nullable()->after('superseded_at')
                        ->constrained('metric_import_batches')->nullOnDelete();
                }
            });

            Schema::table('imported_criteria_values', function (Blueprint $table) {
                try {
                    $table->dropUnique('uniq_imported_value_per_period');
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->unique(['user_id','assessment_period_id','performance_criteria_id','is_active'], 'uniq_imported_value_active');
                $table->index(['assessment_period_id','is_active'], 'idx_imported_values_period_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('imported_criteria_values')) {
            Schema::table('imported_criteria_values', function (Blueprint $table) {
                try { $table->dropUnique('uniq_imported_value_active'); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_imported_values_period_active'); } catch (\Throwable $e) {}

                $table->unique(['user_id','assessment_period_id','performance_criteria_id'], 'uniq_imported_value_per_period');

                if (Schema::hasColumn('imported_criteria_values', 'superseded_by_batch_id')) {
                    try { $table->dropForeign(['superseded_by_batch_id']); } catch (\Throwable $e) {}
                }

                foreach (['superseded_by_batch_id','superseded_at','is_active'] as $col) {
                    if (Schema::hasColumn('imported_criteria_values', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('metric_import_batches')) {
            Schema::table('metric_import_batches', function (Blueprint $table) {
                try { $table->dropUnique('uniq_metric_active_period_key'); } catch (\Throwable $e) {}

                if (Schema::hasColumn('metric_import_batches', 'previous_batch_id')) {
                    try { $table->dropForeign(['previous_batch_id']); } catch (\Throwable $e) {}
                }

                foreach (['active_period_key','previous_batch_id','is_superseded'] as $col) {
                    if (Schema::hasColumn('metric_import_batches', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
