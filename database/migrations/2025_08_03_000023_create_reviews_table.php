<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();

            $t->foreignId('visit_id')
            ->constrained('visits')
                ->cascadeOnDelete();

            $t->unsignedTinyInteger('overall_rating')->nullable();
            $t->text('comment')->nullable();

            // identitas ringan (tanpa akun pasien)
            $t->string('patient_name')->nullable();
            $t->string('contact')->nullable();

            // anti-spam / audit
            $t->string('client_ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();

            $t->timestamps();

            // jika hanya SATU ulasan per kunjungan:
            $t->unique('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }

};
