<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_ranking_runs', function (Blueprint $table) {
            $table->id();
            $table->date('analysis_date');
            $table->string('ranking_version')->default('1.0.0');
            $table->string('engine_version')->default('1.0.0');
            $table->string('dataset_version')->default('1.0.0');
            $table->json('config_snapshot')->nullable();
            $table->timestamp('cutoff_at')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_ranking_runs');
    }
};
