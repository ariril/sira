<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('multi_rater_assessments', function (Blueprint $table) {
            $table->unique(
                ['assessee_id', 'assessment_period_id', 'assessor_type', 'assessor_id'],
                'uniq_mra_once_per_assessor_type'
            );
        });
    }

    public function down(): void
    {
        Schema::table('multi_rater_assessments', function (Blueprint $table) {
            $table->dropUnique('uniq_mra_once_per_assessor_type');
        });
    }
};
