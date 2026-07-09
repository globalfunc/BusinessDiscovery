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
        Schema::create('proposal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_owner_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('markdown')->nullable(); // null for externally-written uploads (§6.4)
            $table->string('generated_by'); // ai | manual | uploaded
            $table->foreignId('upload_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('attachments')->nullable(); // upload ids attached to this version
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
        Schema::dropIfExists('proposal_documents');
    }
};
