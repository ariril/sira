<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profession_reporting_lines')) {
            return;
        }

        Schema::create('profession_reporting_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessee_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->foreignId('assessor_profession_id')->constrained('professions')->cascadeOnDelete();
            $table->enum('relation_type', ['supervisor', 'peer', 'subordinate'])->default('supervisor');
            $table->unsignedInteger('level')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([
                'assessee_profession_id',
                'assessor_profession_id',
                'relation_type',
                'level',
            ], 'uniq_prof_reporting_line');
        });

        // Best-effort conditional constraints (skip if driver doesn't support CHECK)
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            try {
                // level required for supervisor; NULL otherwise
                DB::statement("ALTER TABLE profession_reporting_lines ADD CONSTRAINT chk_prl_level_supervisor CHECK ((relation_type = 'supervisor' AND level IS NOT NULL AND level >= 1) OR (relation_type <> 'supervisor' AND level IS NULL))");
            } catch (\Throwable $e) {
                // ignore (driver/version may not support CHECK constraints)
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profession_reporting_lines');
    }
};
