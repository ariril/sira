<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('assessment_periods')) {
            return;
        }

        // Add metadata columns first (safe, nullable)
        Schema::table('assessment_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_periods', 'rejected_level')) {
                $table->unsignedSmallInteger('rejected_level')->nullable()->after('status');
            }
            if (!Schema::hasColumn('assessment_periods', 'rejected_by_id')) {
                $table->foreignId('rejected_by_id')->nullable()->after('rejected_level')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assessment_periods', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by_id');
            }
            if (!Schema::hasColumn('assessment_periods', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('rejected_at');
            }

            if (!Schema::hasColumn('assessment_periods', 'revision_opened_by_id')) {
                $table->foreignId('revision_opened_by_id')->nullable()->after('rejected_reason')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assessment_periods', 'revision_opened_at')) {
                $table->timestamp('revision_opened_at')->nullable()->after('revision_opened_by_id');
            }
            if (!Schema::hasColumn('assessment_periods', 'revision_opened_reason')) {
                $table->text('revision_opened_reason')->nullable()->after('revision_opened_at');
            }

            if (!Schema::hasColumn('assessment_periods', 'approval_attempt')) {
                $table->unsignedSmallInteger('approval_attempt')->default(1)->after('revision_opened_reason');
            }
        });

        // Alter enum/check constraint to add 'revision'
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `assessment_periods` MODIFY `status` ENUM('draft','active','revision','locked','approval','closed') NOT NULL DEFAULT 'draft'");
            return;
        }

        if ($driver === 'pgsql') {
            // Laravel's enum() maps to a CHECK constraint on Postgres.
            DB::statement("ALTER TABLE assessment_periods DROP CONSTRAINT IF EXISTS assessment_periods_status_check");
            DB::statement("ALTER TABLE assessment_periods ADD CONSTRAINT assessment_periods_status_check CHECK (status IN ('draft','active','revision','locked','approval','closed'))");
            return;
        }

        // sqlite / sqlsrv: enum is typically stored as string; nothing to do.
    }

    public function down(): void
    {
        if (!Schema::hasTable('assessment_periods')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `assessment_periods` MODIFY `status` ENUM('draft','active','locked','approval','closed') NOT NULL DEFAULT 'draft'");
        }
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE assessment_periods DROP CONSTRAINT IF EXISTS assessment_periods_status_check");
            DB::statement("ALTER TABLE assessment_periods ADD CONSTRAINT assessment_periods_status_check CHECK (status IN ('draft','active','locked','approval','closed'))");
        }

        Schema::table('assessment_periods', function (Blueprint $table) {
            $cols = [
                'rejected_level',
                'rejected_by_id',
                'rejected_at',
                'rejected_reason',
                'revision_opened_by_id',
                'revision_opened_at',
                'revision_opened_reason',
                'approval_attempt',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('assessment_periods', $col)) {
                    if (in_array($col, ['rejected_by_id', 'revision_opened_by_id'], true)) {
                        // Drop FK first (Laravel names can vary, so dropForeign by column is safest)
                        try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
