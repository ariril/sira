<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_tasks', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('start_date');
            $table->time('due_time')->nullable()->after('due_date');
        });

        DB::table('additional_tasks')
            ->whereNull('start_time')
            ->update(['start_time' => '00:00:00']);

        DB::table('additional_tasks')
            ->whereNull('due_time')
            ->update(['due_time' => '23:59:00']);
    }

    public function down(): void
    {
        Schema::table('additional_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('additional_tasks', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('additional_tasks', 'due_time')) {
                $table->dropColumn('due_time');
            }
        });
    }
};
