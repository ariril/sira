<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_assessment_details', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_assessment_details', 'criteria_metric_id')) {
                $table->foreignId('criteria_metric_id')->nullable()->after('performance_criteria_id')
                    ->constrained('criteria_metrics')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('performance_assessment_details', function (Blueprint $table) {
            if (Schema::hasColumn('performance_assessment_details', 'criteria_metric_id')) {
                $table->dropConstrainedForeignId('criteria_metric_id');
            }
        });
    }
};
