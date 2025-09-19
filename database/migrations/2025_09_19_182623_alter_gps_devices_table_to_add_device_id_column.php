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
        Schema::table('gps_devices', function (Blueprint $table) {
            $table->string('device_id', 191)->after('uri_gps')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gps_devices', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
