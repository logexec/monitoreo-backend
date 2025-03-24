<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_metadatas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->string('estimated_duration')->nullable();
            $table->string('actual_duration')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('cargo_type')->nullable();
            $table->decimal('cargo_weight', 8, 2)->nullable();
            $table->text('special_requirements')->nullable();
            $table->string('customer_reference')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('external_notes')->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_metadatas');
    }
};
