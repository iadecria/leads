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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->integer('request_count')->default(0);
            $table->timestamp('last_request_at')->nullable();
            $table->integer('cache_hits')->default(0);
            $table->integer('cache_misses')->default(0);
            $table->timestamps();

            $table->unique('endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
