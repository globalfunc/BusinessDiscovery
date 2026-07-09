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
        Schema::create('advisory_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_owner_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->string('module')->nullable();
            $table->jsonb('brief')->nullable();
            $table->string('verdict');
            $table->string('drop_reason')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->jsonb('exemplars')->nullable();
            $table->foreignId('dcp_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_owner_id', 'phase', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_briefs');
    }
};
