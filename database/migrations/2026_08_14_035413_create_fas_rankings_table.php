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
            $table->unsignedBigInteger('fas_run_id');
            $table->unsignedBigInteger('fas_event_id');
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
