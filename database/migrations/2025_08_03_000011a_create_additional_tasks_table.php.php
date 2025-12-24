<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->onDelete('cascade');

            $table->foreignId('assessment_period_id')
                ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('policy_doc_path')->nullable();

            $table->date('start_date');
            $table->date('due_date');
            $table->time('start_time')->default('00:00:00');
            $table->time('due_time')->default('23:59:00');

            $table->decimal('bonus_amount', 15, 2)->nullable();
            $table->decimal('points', 8, 2)->nullable();

            $table->tinyInteger('max_claims')->default(1);

            $table->enum('status', ['draft','open','closed','cancelled'])
                ->default('open');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['unit_id','assessment_period_id','status'], 'idx_task_unit_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_tasks');
    }
};
