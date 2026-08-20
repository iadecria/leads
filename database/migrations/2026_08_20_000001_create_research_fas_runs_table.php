<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_fas_runs', function (Blueprint $table) {
            $table->id();
            $table->date('analysis_date');
            $table->integer('window')->nullable(); // 1 | 2 | null = todos
            $table->string('status')->default('PENDING'); // PENDING, RUNNING, COMPLETED, FAILED, PARTIAL
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('input_fixtures')->nullable();
            $table->json('result')->nullable();     // { games, top3, top5, best_games, ranking }
            $table->json('debug')->nullable();      // calls, tokens, cost, sources
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_fas_runs');
    }
};
