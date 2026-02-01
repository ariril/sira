<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('performance_assessment_details')) {
            return;
        }

        if (!Schema::hasColumn('performance_assessment_details', 'criteria_metric_id')) {
            return;
        }

        Schema::table('performance_assessment_details', function (Blueprint $table) {
            // Drop FK first (name is convention-based).
            $table->dropForeign(['criteria_metric_id']);
            $table->dropColumn('criteria_metric_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('performance_assessment_details')) {
            return;
        }

        if (Schema::hasColumn('performance_assessment_details', 'criteria_metric_id')) {
            return;
        }

        Schema::table('performance_assessment_details', function (Blueprint $table) {
            $table->foreignId('criteria_metric_id')
                ->nullable()
                ->constrained('imported_criteria_values')
                ->nullOnDelete();
        });
    }
};
