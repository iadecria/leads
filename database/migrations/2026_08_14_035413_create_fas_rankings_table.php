<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fas_run_id')->constrained('fas_runs')->onDelete('cascade');
            $table->foreignId('fas_event_id')->constrained('fas_events')->onDelete('cascade');
            $table->string('ranking_type');
            $table->integer('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_rankings');
    }
};
