<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->onDelete('cascade');
            $table->foreignId('home_team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('away_team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('season');
            $table->string('round')->nullable();
            $table->dateTime('fixture_date');
            $table->string('venue')->nullable();
            $table->string('status')->default('SCHEDULED');
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->integer('halftime_home_score')->nullable();
            $table->integer('halftime_away_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
