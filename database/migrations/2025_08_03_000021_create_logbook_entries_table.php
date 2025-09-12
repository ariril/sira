<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('entry_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('activity');

            // Metadata
            $table->string('category', 50)->nullable()->index();
            $table->enum('status', [
                'draf',
                'diajukan',
                'disetujui',
                'ditolak',
            ])->default('diajukan')->index();

            $table->foreignId('approver_id')
            ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->json('attachments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
