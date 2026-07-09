<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §6.6 phase-copy overrides: per-phase title/helper text, plus the
     * "greeting" pseudo-phase's title/body (the default greeting template,
     * distinct from a BO's own `greeting_override`). Runtime reads these as
     * a shared Inertia prop merged over the static bg/en lang JSON — see
     * HandleInertiaRequests::share() and resources/js/lib/i18n.ts.
     */
    public function up(): void
    {
        Schema::create('phase_copy_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('phase');
            $table->string('language');
            $table->text('title')->nullable();
            $table->text('helper')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['phase', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_copy_overrides');
    }
};
