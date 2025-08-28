<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('abouts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'visi',
                'misi',
                'struktur',
                'profil_rs',
                'tugas_fungsi'
            ])->unique();

            $table->string('title', 200)->nullable();
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->json('attachments_json')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abouts');
    }
};
