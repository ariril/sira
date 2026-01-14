<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_assessments', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_assessments', 'total_wsm_value_score')) {
                $table->decimal('total_wsm_value_score', 8, 2)
                    ->nullable()
                    ->after('total_wsm_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('performance_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('performance_assessments', 'total_wsm_value_score')) {
                $table->dropColumn('total_wsm_value_score');
            }
        });
    }
};
