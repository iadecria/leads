<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fas_rankings', function (Blueprint $table) {
            $table->dropForeign(['fas_run_id']);
            $table->dropColumn('fas_run_id');

            $table->foreignId('fas_ranking_run_id')->constrained('fas_ranking_runs')->onDelete('cascade');

            $table->decimal('candidate_score', 5, 2)->nullable();
            $table->json('penalties')->nullable();
            $table->string('watchlist_reason')->nullable();
            $table->string('correlation_group')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fas_rankings', function (Blueprint $table) {
            $table->dropForeign(['fas_ranking_run_id']);
            $table->dropColumn(['fas_ranking_run_id', 'candidate_score', 'penalties', 'watchlist_reason', 'correlation_group']);

            $table->foreignId('fas_run_id')->nullable()->constrained('fas_runs')->onDelete('cascade');
        });
    }
};
