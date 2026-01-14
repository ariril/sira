<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft','active','revision','locked','approval','closed'])->default('draft');

            // Rejection metadata
            $table->unsignedSmallInteger('rejected_level')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            // Revision metadata
            $table->foreignId('revision_opened_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_opened_at')->nullable();
            $table->text('revision_opened_reason')->nullable();

            $table->unsignedSmallInteger('approval_attempt')->default(1);

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // =========================================================
        // Related tables (placed near assessment periods group)
        // =========================================================
        Schema::create('assessment_period_user_membership_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id');
            $table->foreignId('user_id');
            $table->foreignId('unit_id');
            $table->foreignId('profession_id')->nullable();

            $table->timestamp('snapshotted_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_period_id', 'user_id'], 'uniq_period_user_membership');

            // Short FK names to avoid MySQL identifier length limits.
            $table->foreign('assessment_period_id', 'fk_apums_period')
                ->references('id')->on('assessment_periods')->onDelete('cascade');
            $table->foreign('user_id', 'fk_apums_user')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('unit_id', 'fk_apums_unit')
                ->references('id')->on('units')->onDelete('cascade');
            $table->foreign('profession_id', 'fk_apums_prof')
                ->references('id')->on('professions')->nullOnDelete();
        });

        Schema::create('assessment_period_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 80);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['assessment_period_id', 'action'], 'idx_period_audit_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_period_audit_logs');
        Schema::dropIfExists('assessment_period_user_membership_snapshots');
        Schema::dropIfExists('assessment_periods');
    }

};
