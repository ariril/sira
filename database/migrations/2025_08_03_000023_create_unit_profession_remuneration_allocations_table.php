<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_profession_remuneration_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_period_id');
            $table->foreignId('unit_id');
            $table->foreignId('profession_id')->nullable();

            // Jumlah alokasi uang untuk unit tersebut
            $table->decimal('amount', 15, 2)->default(0);

            $table->text('note')->nullable();

            // Tanggal kapan alokasi dipublish (resmi dipakai)
            $table->timestamp('published_at')->nullable();

            // User yang merevisi terakhir
            $table->foreignId('revised_by')->nullable();

            $table->timestamps();

            // Satu kombinasi periode + unit + profesi hanya boleh satu baris
            $table->unique(['assessment_period_id', 'unit_id', 'profession_id'], 'uniq_period_unit_profession');

            // Foreign keys with shorter names to avoid MySQL identifier length limits
            $table->foreign('assessment_period_id', 'fk_upr_alloc_period')
                ->references('id')->on('assessment_periods')->onDelete('cascade');
            $table->foreign('unit_id', 'fk_upr_alloc_unit')
                ->references('id')->on('units')->onDelete('cascade');
            $table->foreign('profession_id', 'fk_upr_alloc_profession')
                ->references('id')->on('professions')->nullOnDelete();
            $table->foreign('revised_by', 'fk_upr_alloc_revised_by')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_profession_remuneration_allocations');
    }
};
