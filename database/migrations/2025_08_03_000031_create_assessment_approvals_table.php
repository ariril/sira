<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('performance_assessment_id')
                ->constrained('performance_assessments')
                ->cascadeOnDelete();

            $table->foreignId('approver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('level');
            $table->unsignedSmallInteger('attempt')->default(1);

            $table->enum('status', ['pending','approved','rejected'])
                ->default('pending');

            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();

            $table->timestamp('invalidated_at')->nullable();
            $table->foreignId('invalidated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('invalidated_reason')->nullable();

            $table->timestamps();

            $table->unique(['performance_assessment_id', 'level', 'attempt'], 'uniq_assessment_level_attempt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_approvals');
    }
};
