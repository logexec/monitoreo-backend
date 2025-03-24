<?php

namespace App\Imports;

use App\Models\Trip;
use App\Rules\DriverExists;
use App\Rules\VehicleExists;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class TripsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    /**
     * Mapear cada fila a un modelo Trip.
     * Se asume que la plantilla tiene las cabeceras:
     * system_trip_id, external_trip_id, delivery_date, driver_name, driver_email,
     * driver_phone, origin, destination, project, plate_number, property_type,
     * shift, gps_provider.
     */
    public function model(array $row)
    {
        return new Trip([
            'system_trip_id'   => $row['system_trip_id'],
            'external_trip_id' => $row['external_trip_id'] ?? null,
            'delivery_date'    => $row['delivery_date'],
            'driver_name'      => $row['driver_name'],
            'driver_document'     => $row['driver_document'] ?? null,
            'driver_phone'     => $row['driver_phone'] ?? null,
            'origin'           => $row['origin'] ?? null,
            'destination'      => $row['destination'],
            'project'          => $row['project'],
            'plate_number'     => $row['plate_number'],
            'property_type'    => $row['property_type'],
            'shift'            => $row['shift'],
            'gps_provider'     => $row['gps_provider'] ?? null,
            'current_status'   => 'SCHEDULED',
        ]);
    }

    /**
     * Reglas de validación para cada fila.
     * Aquí se validan datos básicos y se invocan las reglas personalizadas.
     */
    public function rules(): array
    {
        return [
            'system_trip_id'   => 'required|string|unique:trips,system_trip_id',
            'external_trip_id' => 'nullable|string',
            'delivery_date'    => 'required|date',
            'driver_name'      => 'required|string',
            'driver_document' => ['required', new DriverExists],
            'driver_phone'     => 'nullable|string',
            'origin'           => 'nullable|string',
            'destination'      => 'required|string',
            'project'          => 'required|string',
            'plate_number'     => ['required', new VehicleExists],
            'property_type'    => 'required|string',
            'shift'            => 'required|string',
            'gps_provider'     => 'nullable|string',
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function customValidationMessages()
    {
        return [
            'system_trip_id.required' => 'El ID del viaje es obligatorio.',
            'delivery_date.required'  => 'La fecha de entrega es obligatoria.',
            // Otros mensajes según se requiera...
        ];
    }
}
