<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->decimal('fuel_cost', 10, 2)->nullable();
            $table->decimal('toll_cost', 10, 2)->nullable();
            $table->decimal('personnel_cost', 10, 2)->nullable();
            $table->decimal('other_costs', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2);
            $table->decimal('revenue', 10, 2)->nullable();
            $table->decimal('margin', 10, 2);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_costs');
    }
};
