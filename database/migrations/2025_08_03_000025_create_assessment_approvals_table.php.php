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

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            $table->unique(['performance_assessment_id', 'level'], 'uniq_assessment_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_approvals');
    }

};
