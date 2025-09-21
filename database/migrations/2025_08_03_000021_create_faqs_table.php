<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();

            $table->string('question');
            $table->longText('answer');
            $table->unsignedSmallInteger('order')->default(0); // urutan
            $table->boolean('is_active')->default(true);
            $table->string('category', 50)->nullable();

            $table->timestamps();

            $table->index('order', 'faqs_order_index');
            $table->index('is_active', 'faqs_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }

};
