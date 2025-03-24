<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_personnels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->uuid('personnel_id');
            $table->string('role');
            $table->date('assignment_date');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            // Puedes agregar una foreign key para personnel_id si tienes una tabla de personal.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_personnels');
    }
};
