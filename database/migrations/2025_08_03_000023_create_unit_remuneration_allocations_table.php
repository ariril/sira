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

            // Opsional pembagian per profesi (single-table design)
            $table->foreignId('profession_id')
                ->nullable()
                ->constrained('professions')
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

            // Satu kombinasi periode + unit + profesi hanya boleh satu baris
            $table->unique(['assessment_period_id', 'unit_id', 'profession_id'], 'uniq_period_unit_profession');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_remuneration_allocations');
    }
};
