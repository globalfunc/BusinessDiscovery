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
        Schema::table('business_owners', function (Blueprint $table) {
            // §7.7/§0.1: per-BO override of config('ai.per_bo_token_cap'); null
            // means "use the global default" (S4.8 gets the full settings UI).
            $table->unsignedInteger('ai_token_cap')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_owners', function (Blueprint $table) {
            $table->dropColumn('ai_token_cap');
        });
    }
};
