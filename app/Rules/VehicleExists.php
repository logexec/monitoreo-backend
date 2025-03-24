<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class VehicleExists implements Rule
{
    public function passes($attribute, $value)
    {
        // Normaliza el valor de la placa: elimina espacios y guiones, lo pone en mayúsculas
        $normalizedInput = strtoupper(preg_replace('/[\s-]+/', '', $value));

        // Buscamos en la conexión tms, tabla vehiculos
        // Usamos REPLACE anidados para eliminar espacios y guiones en el valor almacenado
        $vehicle = DB::connection('tms')
            ->table('vehiculos')
            ->whereRaw("UPPER(REPLACE(REPLACE(placa, ' ', ''), '-', '')) = ?", [$normalizedInput])
            ->first();
        return $vehicle !== null;
    }

    public function message()
    {
        return 'El vehículo con el número de placa proporcionado no se encuentra registrado en el sistema.';
    }
}
