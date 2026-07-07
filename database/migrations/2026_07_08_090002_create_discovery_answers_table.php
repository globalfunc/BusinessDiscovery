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
        Schema::create('discovery_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_session_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->string('field_key');
            $table->jsonb('value')->nullable();
            $table->timestamps();

            $table->unique(['discovery_session_id', 'phase', 'field_key'], 'discovery_answers_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovery_answers');
    }
};
