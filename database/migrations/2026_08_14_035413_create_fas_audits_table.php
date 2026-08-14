<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fas_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fas_event_id')->constrained('fas_events')->onDelete('cascade');
            $table->string('status')->default('PENDING');
            $table->string('result_value')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fas_audits');
    }
};
