<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_execution_runs', function (Blueprint $table) {
            $table->id();
            $table->string('execution_type'); // DAILY_ANALYSIS, RESULT_AUDIT
            $table->date('analysis_date');
            $table->string('status'); // PENDING, RUNNING, COMPLETED, FAILED, PARTIAL, SKIPPED

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->string('current_step')->nullable();

            $table->string('fixtures_status')->nullable();
            $table->string('datasets_status')->nullable();
            $table->string('analysis_status')->nullable();
            $table->string('ranking_status')->nullable();
            $table->string('results_status')->nullable();
            $table->string('audit_status')->nullable();

            $table->json('summary')->nullable();
            $table->json('errors')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_execution_runs');
    }
};
