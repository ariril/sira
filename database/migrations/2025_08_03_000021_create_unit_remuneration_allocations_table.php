<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_remuneration_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->onDelete('cascade');

            $table->foreignId('unit_id')
                ->constrained('units')
                ->onDelete('cascade');

            // Jumlah alokasi uang untuk unit tersebut
            $table->decimal('amount', 15, 2)->default(0);

            $table->text('note')->nullable();

            // Tanggal kapan alokasi dipublish (resmi dipakai)
            $table->timestamp('published_at')->nullable();

            // User yang merevisi terakhir
            $table->foreignId('revised_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Satu unit hanya boleh punya 1 alokasi per periode
            $table->unique(['unit_id', 'assessment_period_id'], 'uniq_unit_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_remuneration_allocations');
    }
};
