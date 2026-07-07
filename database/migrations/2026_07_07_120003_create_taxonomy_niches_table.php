<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taxonomy_niches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_category_id')->constrained()->cascadeOnDelete();
            $table->jsonb('name');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomy_niches');
    }
};
