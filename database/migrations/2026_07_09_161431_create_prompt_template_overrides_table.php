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
        Schema::create('prompt_template_overrides', function (Blueprint $table) {
            $table->id();
            // §6.7 prompt template viewer/editor: one row per saved version of
            // a tool's system prompt. Rows are never deleted (version history);
            // "reset to default" deactivates every row for the tool so
            // PromptTemplateRegistry::get() falls back to the hardcoded
            // PromptTemplate class again.
            $table->string('tool');
            $table->unsignedInteger('version');
            $table->text('system_prompt');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tool', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_template_overrides');
    }
};
