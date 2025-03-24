<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class DriverExists implements Rule
{
    public function passes($attribute, $value)
    {
        // Normalizamos el valor si es necesario (por ejemplo, quitando posibles espacios en blanco)
        $normalizedValue = trim($value);

        // Buscamos en la conexión sistema_onix, tabla onix_personal
        $driver = DB::connection('sistema_onix')
            ->table('onix_personal')
            ->where('name', $normalizedValue) // Esta columna contiene el número de cédula de identidad.
            ->first();
        return $driver !== null;
    }

    public function message()
    {
        return 'La cédula de identidad proporcionada no se encuentra registrada en el sistema.';
    }
}
