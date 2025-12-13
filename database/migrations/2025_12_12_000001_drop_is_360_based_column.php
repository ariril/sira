<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (Schema::hasColumn('performance_criterias', 'is_360_based')) {
                $table->dropColumn('is_360_based');
            }
        });
    }

    public function down(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_criterias', 'is_360_based')) {
                $table->boolean('is_360_based')->default(false);
            }
        });
    }
};
