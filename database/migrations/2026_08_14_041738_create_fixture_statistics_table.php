<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fixture_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('shots_total')->nullable();
            $table->integer('shots_on_goal')->nullable();
            $table->integer('shots_off_goal')->nullable();
            $table->integer('blocked_shots')->nullable();
            $table->integer('shots_inside_box')->nullable();
            $table->integer('shots_outside_box')->nullable();
            $table->string('possession')->nullable();
            $table->integer('corners')->nullable();
            $table->integer('yellow_cards')->nullable();
            $table->integer('red_cards')->nullable();
            $table->integer('fouls')->nullable();
            $table->integer('offsides')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['fixture_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixture_statistics');
    }
};
