<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure FK on assessment_period_id still has an index before dropping composite unique
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            // Add a plain index for the FK if none exists
            try { $table->index('assessment_period_id', 'idx_att_batch_period'); } catch (\Throwable $e) {}
        });

        // Drop the broad unique (assessment_period_id, is_superseded)
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            try { $table->dropUnique('uniq_period_active_batch'); } catch (\Throwable $e) {}
        });

        // Add a generated column that is the period id only when active (not superseded)
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_import_batches', 'active_period_key')) {
                $table->unsignedBigInteger('active_period_key')->nullable()
                    ->storedAs('(case when (is_superseded = 0) then assessment_period_id else null end)')
                    ->after('previous_batch_id');
            }
        });

        // Unique only among active rows because NULLs don't collide
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            $table->unique(['active_period_key'], 'uniq_active_period_key');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            try { $table->dropUnique('uniq_active_period_key'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('attendance_import_batches', 'active_period_key')) {
                $table->dropColumn('active_period_key');
            }
            // Drop the auxiliary index if we created it
            try { $table->dropIndex('idx_att_batch_period'); } catch (\Throwable $e) {}
        });

        // Restore the previous unique
        Schema::table('attendance_import_batches', function (Blueprint $table) {
            $table->unique(['assessment_period_id','is_superseded'], 'uniq_period_active_batch');
        });
    }
};
