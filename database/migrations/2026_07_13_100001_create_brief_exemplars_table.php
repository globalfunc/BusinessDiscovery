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
        Schema::create('brief_exemplars', function (Blueprint $table) {
            $table->id();
            $table->jsonb('context_tags');
            $table->text('dcp_excerpt');
            $table->jsonb('exemplar_brief');
            $table->text('quality_notes')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brief_exemplars');
    }
};
