<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fas_audits', function (Blueprint $table) {
            $table->foreignId('fas_ranking_run_id')->after('id')->constrained('fas_ranking_runs')->onDelete('cascade');
            $table->foreignId('fas_ranking_id')->after('fas_ranking_run_id')->constrained('fas_rankings')->onDelete('cascade');
            $table->foreignId('fixture_id')->after('fas_event_id')->constrained('fixtures')->onDelete('cascade');
            $table->string('audit_version')->after('validated_at')->default('1.0.0');
            $table->string('ranking_version')->after('audit_version')->nullable();
            $table->string('engine_version')->after('ranking_version')->nullable();
            $table->string('dataset_version')->after('engine_version')->nullable();
            $table->json('payload')->after('dataset_version')->nullable();

            // Ensure idempotency
            $table->unique(['fas_ranking_run_id', 'fas_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fas_audits', function (Blueprint $table) {
            $table->dropUnique(['fas_ranking_run_id', 'fas_event_id']);
            $table->dropForeign(['fas_ranking_run_id']);
            $table->dropForeign(['fas_ranking_id']);
            $table->dropForeign(['fixture_id']);

            $table->dropColumn([
                'fas_ranking_run_id',
                'fas_ranking_id',
                'fixture_id',
                'audit_version',
                'ranking_version',
                'engine_version',
                'dataset_version',
                'payload',
            ]);
        });
    }
};
