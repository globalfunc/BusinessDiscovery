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
        Schema::create('spec_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('markdown');
            $table->string('generated_by'); // ai | fallback (§8)
            $table->text('change_summary')->nullable();
            $table->jsonb('model_meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['discovery_session_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spec_documents');
    }
};
