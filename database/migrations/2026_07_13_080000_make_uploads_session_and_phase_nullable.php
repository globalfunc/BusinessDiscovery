<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * S4.5: uploads gains an admin-side use — externally-written proposal
     * files (§6.4) — which belong to a BO but not to any discovery session
     * or phase.
     */
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->foreignId('discovery_session_id')->nullable()->change();
            $table->string('phase')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->foreignId('discovery_session_id')->nullable(false)->change();
            $table->string('phase')->nullable(false)->change();
        });
    }
};
