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
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('system_trip_id')->unique();
            $table->string('external_trip_id')->nullable();
            $table->date('delivery_date');
            $table->string('driver_name');
            $table->string('driver_document')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination');
            $table->string('project');
            $table->string('plate_number');
            $table->string('property_type');
            $table->enum('shift', ['Día', 'Noche'])->default('Día');
            $table->string('uri_gps')->nullable();
            $table->string('usuario')->nullable();
            $table->string('clave')->nullable();
            $table->string('gps_provider')->nullable();
            $table->enum('current_status', ['SCHEDULED', 'IN_TRANSIT', 'DELAYED', 'DELIVERED', 'CANCELLED'])
                ->default('SCHEDULED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
