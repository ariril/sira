<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('additional_task_claims', function (Blueprint $table) {
            // Drop old unique index and helper column if they exist
            $table->dropUnique('uniq_task_single_active');
            if (Schema::hasColumn('additional_task_claims', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('additional_task_claims', function (Blueprint $table) {
            // Only enforce uniqueness when status = 'active'
            $table->unsignedBigInteger('active_task_key')
                ->nullable()
                ->storedAs("case when status = 'active' then additional_task_id else null end");

            $table->unique('active_task_key', 'uniq_task_single_active');
        });
    }

    public function down(): void
    {
        Schema::table('additional_task_claims', function (Blueprint $table) {
            $table->dropUnique('uniq_task_single_active');
            if (Schema::hasColumn('additional_task_claims', 'active_task_key')) {
                $table->dropColumn('active_task_key');
            }
        });

        Schema::table('additional_task_claims', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_active')
                ->storedAs("CASE WHEN status = 'active' THEN 1 ELSE 0 END");
            $table->unique(['additional_task_id', 'is_active'], 'uniq_task_single_active');
        });
    }
};
