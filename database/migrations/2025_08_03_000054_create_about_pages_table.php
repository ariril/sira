<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table) {
            $table->id();

            $table->enum('type', [
                'visi',
                'misi',
                'struktur',
                'profil_rs',
                'tugas_fungsi',
            ])->unique('about_pages_type_unique');

            $table->string('title', 200)->nullable();
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->json('attachments')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('published_at', 'about_pages_published_index');
            $table->index('is_active', 'about_pages_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }

};
