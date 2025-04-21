<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('trip_id')->constrained()->onDelete('cascade');
            $table->string('gps_provider')->nullable();
            $table->string('uri_gps')->nullable();
            $table->string('user')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_devices');
    }
};
