<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed vendor blocklist (§7.6.2, §6.6). A dedicated table rather than
 * a single `settings` jsonb blob (which §8 pointed at): one row per term gives
 * natural CRUD + per-term flags (is_regex, active, replacement), avoids
 * read-modify-write races on a shared blob when the editor grows, and keeps the
 * output filter's lookup a plain indexed query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_blocklist_terms', function (Blueprint $table) {
            $table->id();
            // A literal brand/vendor name, or (when is_regex) a PCRE body sans delimiters.
            $table->string('term');
            $table->boolean('is_regex')->default(false);
            // Generic label the filter swaps a matched term for; null → config default.
            $table->string('replacement')->nullable();
            $table->boolean('active')->default(true);
            // Optional admin memo (e.g. "booking tools", "email/CRM").
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_blocklist_terms');
    }
};
