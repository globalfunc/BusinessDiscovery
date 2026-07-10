<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S5.7 — LLM-judge grading metadata on advisory_briefs. `verdict` is a
     * plain string column, so the new hidden_low_value state is enum-only
     * (no schema change). `label` is the admin's ground-truth calibration
     * mark: "good" | "bad" | null (unreviewed).
     */
    public function up(): void
    {
        Schema::table('advisory_briefs', function (Blueprint $table) {
            $table->jsonb('scores')->nullable()->after('drop_reason');
            $table->decimal('composite', 4, 2)->nullable()->after('scores');
            $table->string('judge_model')->nullable()->after('composite');
            $table->unsignedInteger('rubric_version')->nullable()->after('judge_model');
            $table->string('label')->nullable()->after('rubric_version');

            $table->index('verdict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisory_briefs', function (Blueprint $table) {
            $table->dropIndex(['verdict']);
            $table->dropColumn(['scores', 'composite', 'judge_model', 'rubric_version', 'label']);
        });
    }
};
