<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fas_run_id')->nullable()->constrained('fas_runs')->onDelete('cascade');
            $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
            $table->integer('fii_score')->nullable();
            $table->integer('data_quality_score')->nullable();
            $table->decimal('home_win_probability', 5, 2)->nullable();
            $table->decimal('draw_probability', 5, 2)->nullable();
            $table->decimal('away_win_probability', 5, 2)->nullable();
            $table->decimal('over_1_5_probability', 5, 2)->nullable();
            $table->decimal('over_2_5_probability', 5, 2)->nullable();
            $table->decimal('btts_probability', 5, 2)->nullable();
            $table->decimal('first_half_goal_probability', 5, 2)->nullable();
            $table->json('analysis_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_analyses');
    }
};
