<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();

            // Nomor dari SIM RS (tanpa relasi ke visits)
            $t->string('registration_ref', 50);

            // Unit yang dikunjungi (boleh NULL; jika unit dihapus -> NULL)
            $t->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $t->unsignedTinyInteger('overall_rating')->nullable();
            $t->text('comment')->nullable();

            // identitas ringan (tanpa akun pasien)
            $t->string('patient_name')->nullable();
            $t->string('contact')->nullable();

            // anti-spam / audit
            $t->string('client_ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();

            $t->timestamps();

            // satu review per nomor registrasi
            $t->unique('registration_ref', 'reviews_registration_ref_unique');

            // untuk laporan/analitik
            $t->index(['unit_id', 'created_at'], 'idx_reviews_unit_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
