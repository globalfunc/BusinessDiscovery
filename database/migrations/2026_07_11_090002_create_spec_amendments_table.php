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
        Schema::create('spec_amendments', function (Blueprint $table) {
            $table->id();
            // The spec version the instruction was applied to (§8 spec_id).
            $table->foreignId('spec_document_id')->constrained()->cascadeOnDelete();
            $table->text('instruction');
            // Version number of the spec_documents row the amendment produced.
            $table->unsignedInteger('resulting_version');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spec_amendments');
    }
};
