<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (!Schema::hasColumn('performance_criterias', 'data_type')) {
                $table->enum('data_type', ['numeric','percentage','boolean','datetime','text'])
                    ->nullable()
                    ->after('type');
            }
            if (!Schema::hasColumn('performance_criterias', 'input_method')) {
                $table->enum('input_method', ['system','manual','import','360'])
                    ->nullable()
                    ->after('data_type');
            }
            if (!Schema::hasColumn('performance_criterias', 'aggregation_method')) {
                $table->enum('aggregation_method', ['sum','avg','count','latest','custom'])
                    ->nullable()
                    ->after('input_method');
            }
            if (!Schema::hasColumn('performance_criterias', 'is_360_based')) {
                $table->boolean('is_360_based')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('performance_criterias', function (Blueprint $table) {
            if (Schema::hasColumn('performance_criterias', 'is_360_based')) {
                $table->dropColumn('is_360_based');
            }
            if (Schema::hasColumn('performance_criterias', 'aggregation_method')) {
                $table->dropColumn('aggregation_method');
            }
            if (Schema::hasColumn('performance_criterias', 'input_method')) {
                $table->dropColumn('input_method');
            }
            if (Schema::hasColumn('performance_criterias', 'data_type')) {
                $table->dropColumn('data_type');
            }
        });
    }
};
