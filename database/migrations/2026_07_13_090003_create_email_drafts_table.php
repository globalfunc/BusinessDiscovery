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
        Schema::create('email_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_owner_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // warm_tease | follow_up | proposal_cover (§6.5)
            $table->string('language', 5);
            $table->string('subject');
            $table->text('body');
            $table->jsonb('model_meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_drafts');
    }
};
