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
        Schema::create('assessment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_owner_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('markdown');
            $table->string('generated_by'); // ai | manual (§6.4 — admin-edited saves)
            $table->jsonb('model_meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['business_owner_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_documents');
    }
};
