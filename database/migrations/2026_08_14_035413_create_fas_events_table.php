<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fas_analysis_id')->constrained('fas_analyses')->onDelete('cascade');
            $table->string('event_type');
            $table->decimal('line', 5, 2)->nullable();
            $table->decimal('estimated_probability', 5, 2)->nullable();
            $table->integer('fas_score')->nullable();
            $table->string('confidence')->nullable();
            $table->boolean('eligible_top3')->default(false);
            $table->boolean('eligible_top5')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_events');
    }
};
