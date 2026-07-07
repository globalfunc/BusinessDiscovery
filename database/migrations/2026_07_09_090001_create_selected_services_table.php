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
        Schema::create('selected_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('custom')->default(false);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('features')->nullable();
            $table->boolean('priority')->default(false);
            $table->text('note')->nullable();
            $table->string('origin')->default('catalog');
            $table->jsonb('reference_links')->nullable();
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            $table->timestamps();

            $table->unique(['discovery_session_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selected_services');
    }
};
