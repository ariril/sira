<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_period_user_membership_snapshots');
    }
};
