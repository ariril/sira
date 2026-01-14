<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('assessment_approvals')) {
            return;
        }

        Schema::table('assessment_approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_approvals', 'attempt')) {
                $table->unsignedSmallInteger('attempt')->default(1)->after('level');
            }

            if (!Schema::hasColumn('assessment_approvals', 'invalidated_at')) {
                $table->timestamp('invalidated_at')->nullable()->after('acted_at');
            }
            if (!Schema::hasColumn('assessment_approvals', 'invalidated_by_id')) {
                $table->foreignId('invalidated_by_id')->nullable()->after('invalidated_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('assessment_approvals', 'invalidated_reason')) {
                $table->text('invalidated_reason')->nullable()->after('invalidated_by_id');
            }
        });

        // Adjust unique key to include attempt
        Schema::table('assessment_approvals', function (Blueprint $table) {
            try {
                $table->dropUnique('uniq_assessment_level');
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unique(['performance_assessment_id', 'level', 'attempt'], 'uniq_assessment_level_attempt');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('assessment_approvals')) {
            return;
        }

        Schema::table('assessment_approvals', function (Blueprint $table) {
            try {
                $table->dropUnique('uniq_assessment_level_attempt');
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unique(['performance_assessment_id', 'level'], 'uniq_assessment_level');

            if (Schema::hasColumn('assessment_approvals', 'invalidated_by_id')) {
                try { $table->dropForeign(['invalidated_by_id']); } catch (\Throwable $e) {}
            }

            foreach (['invalidated_reason', 'invalidated_by_id', 'invalidated_at', 'attempt'] as $col) {
                if (Schema::hasColumn('assessment_approvals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
