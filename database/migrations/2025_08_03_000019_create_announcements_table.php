<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            $table->text('summary')->nullable();
            $table->longText('content');

            $table->enum('category', [
                'remunerasi',
                'kinerja',
                'panduan',
                'lainnya',
            ])->default('lainnya');

            $table->enum('label', [
                'penting',
                'info',
                'update',
            ])->nullable();

            $table->boolean('is_featured')->default(false); // disorot

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable()->index();

            $table->json('attachments')->nullable();

            $table->foreignId('author_id')
            ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
