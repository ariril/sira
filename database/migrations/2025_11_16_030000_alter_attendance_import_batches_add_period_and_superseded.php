<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_import_batches', 'assessment_period_id')) {
                $table->foreignId('assessment_period_id')->nullable()
                    ->after('file_name')
                    ->constrained('assessment_periods')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendance_import_batches', 'is_superseded')) {
                $table->boolean('is_superseded')->default(false)->after('failed_rows');
            }
            if (!Schema::hasColumn('attendance_import_batches', 'previous_batch_id')) {
                $table->foreignId('previous_batch_id')->nullable()->after('is_superseded')
                    ->constrained('attendance_import_batches')->nullOnDelete();
            }
        });

        // Unique active batch per period (is_superseded = 0)
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            $table->unique(['assessment_period_id','is_superseded'], 'uniq_period_active_batch');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_import_batches', 'previous_batch_id')) {
                $table->dropConstrainedForeignId('previous_batch_id');
            }
            if (Schema::hasColumn('attendance_import_batches', 'assessment_period_id')) {
                $table->dropConstrainedForeignId('assessment_period_id');
            }
            if (Schema::hasColumn('attendance_import_batches', 'is_superseded')) {
                $table->dropColumn('is_superseded');
            }
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            try { $table->dropUnique('uniq_period_active_batch'); } catch (\Throwable $e) {}
        });
    }
};
