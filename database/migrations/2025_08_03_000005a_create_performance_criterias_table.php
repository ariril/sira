<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_criterias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['benefit', 'cost']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Added metadata columns 
            $table->enum('data_type', ['numeric','percentage','boolean','datetime','text'])->nullable();
            $table->enum('input_method', ['system','manual','import','360','public_review'])->nullable();
            $table->enum('aggregation_method', ['sum','avg','count','latest','custom'])->nullable();

            $table->enum('normalization_basis', ['total_unit','max_unit','average_unit','custom_target'])
                ->default('total_unit');
            $table->decimal('custom_target_value', 12, 2)->nullable();

            // Default usulan bobot (opsional), dipindahkan dari migrasi tambahan (2025_11_03_000020)
            $table->decimal('suggested_weight', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_criterias');
    }
};
