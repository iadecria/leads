<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('name');
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->string('fas_status')->default('UNKNOWN')->after('status');
            $table->string('fas_status_reason')->nullable()->after('fas_status');
            $table->string('timezone')->default('UTC')->after('fixture_date');
            $table->integer('elapsed')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('logo');
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn(['fas_status', 'fas_status_reason', 'timezone', 'elapsed']);
        });
    }
};
