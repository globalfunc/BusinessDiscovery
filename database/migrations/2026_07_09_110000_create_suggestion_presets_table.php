<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-niche/per-phase static Suggestion Cards (§8, §6.6). Two roles:
     * (1) blended into the AI prompt as inspiration (§7.3 block 7), and
     * (2) the graceful-fallback content shown when an AI suggestion call
     * fails, is invalid, or the budget is exhausted (§7.7). The admin editor
     * for these lands in S4.7; this session only needs the storage + reads.
     * `cards` holds an array of Suggestion Card objects in the §7.4 shape.
     */
    public function up(): void
    {
        Schema::create('suggestion_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_niche_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->jsonb('cards');
            $table->timestamps();

            $table->unique(['taxonomy_niche_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_presets');
    }
};
