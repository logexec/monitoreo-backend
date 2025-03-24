<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trip_id');
            $table->enum('category', [
                'INICIO_RUTA',
                'SEGUIMIENTO',
                'ACCIDENTE',
                'AVERIA',
                'ROBO_ASALTO',
                'PERDIDA_CONTACTO',
                'VIAJE_CARGADO',
                'VIAJE_FINALIZADO'
            ]);
            $table->text('notes');
            $table->string('image_url')->nullable();
            $table->uuid('updated_by'); // Se asume que referencia al id de un usuario
            $table->timestamps();

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            // Si tienes una tabla de usuarios con UUID, puedes agregar:
            // $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_updates');
    }
};
