<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_runs', function (Blueprint $table) {
            $table->id();
            $table->date('analysis_date');
            $table->string('status')->default('PENDING');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('algorithm_version')->nullable();
            $table->integer('data_quality_score')->nullable();
            $table->integer('fixtures_found')->default(0);
            $table->integer('fixtures_eligible')->default(0);
            $table->integer('fixtures_analyzed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_runs');
    }
};
