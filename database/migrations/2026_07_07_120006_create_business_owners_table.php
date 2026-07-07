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
        Schema::create('business_owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company');
            $table->string('logo_path')->nullable();
            $table->text('greeting_override')->nullable();
            $table->enum('language', ['bg', 'en'])->nullable();
            $table->text('admin_context')->nullable();
            $table->foreignId('pre_selected_niche_id')->nullable()
                ->constrained('taxonomy_niches')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->enum('current_stage', [
                'prospect',
                'referral_sent',
                'link_visited',
                'discovery_in_progress',
                'discovery_complete',
                'proposal_sent',
                'negotiation',
                'won',
                'lost',
            ])->default('prospect');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_owners');
    }
};
