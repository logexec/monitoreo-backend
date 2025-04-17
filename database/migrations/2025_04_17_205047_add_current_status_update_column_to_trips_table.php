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
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('current_status_update', ['VIAJE_CREADO', 'INICIO_RUTA', 'SEGUIMIENTO', 'ACCIDENTE', 'AVERIA', 'ROBO_ASALTO', 'PERDIDA_CONTACTO', 'VIAJE_CARGADO', 'VIAJE_FINALIZADO'])->default("VIAJE_CREADO")->after('current_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('current_status_update');
        });
    }
};
