<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Trip;

class CargarViajesAutomaticos extends Command
{
    protected $signature = 'viajes:cargar-automaticos';
    protected $description = 'Carga automática de viajes pendientes desde la API de tms1';

    public function handle()
    {
        $apiUrl = env('TMS1_API_URL', 'https://tms1.logex.com.ec/api/get-trips');
        $apiToken = env('TMS1_API_TOKEN', '');

        $this->info('Consultando la API de tms1...');

        $response = Http::withToken($apiToken)->get($apiUrl);

        if (!$response->successful()) {
            $this->error('Error al consultar la API de tms1. Código: ' . $response->status());
            return 1;
        }

        $data = $response->json();

        if (!isset($data['viajes'])) {
            $this->error('La respuesta de la API no contiene la clave "viajes".');
            return 1;
        }

        $countImported = 0;
        foreach ($data['viajes'] as $viaje) {
            // Filtrar solo los viajes que no hayan finalizado
            if (!isset($viaje['estado_viaje']) || $viaje['estado_viaje'] === 'Finalizado') {
                continue;
            }

            // Evitar duplicados
            if (Trip::where('external_trip_id', $viaje['id_viaje'])->exists()) {
                continue;
            }

            // Mapear los campos del API a los del modelo Trip
            $tripData = [
                'system_trip_id'   => $viaje['proyecto'] . '-' . str_pad($viaje['id_viaje'], 5, '0', STR_PAD_LEFT),
                'external_trip_id' => $viaje['id_viaje'] ?? 'No definido',
                'delivery_date'    => $viaje['fecha_viaje'],
                'driver_name'      => $viaje['conductor'] ?? 'Sin asignar',
                'driver_phone'     => $viaje['telefono_conductor'] ?? null,
                'origin'           => $viaje['origen'] ?? "No definido",
                'start_time'       => $viaje['fecha_hora_inicio_viaje'],
                'destination'      => $viaje['destino'] ?? "No definido",
                'project'          => $viaje['proyecto'] ?? "No definido",
                'vehicle_id'       => $viaje['vehiculo_id'] ?? "No definido",
                'plate_number'     => $viaje['placa'] ?? "No definido",
                'property_type'    => $viaje['property_type'] ?? 'No definido',
                'shift'            => $viaje['shift'] ?? 'Día',
                'current_status'   => 'SCHEDULED',
                'created_at'       => $viaje['fecha_creacion_viaje'],
                'updated_at'       => $viaje['fecha_hora_inicio_viaje'],
            ];

            // Crear el viaje
            $trip = Trip::create($tripData);

            // Procesar dispositivos_gps si existen
            if (!empty($viaje['dispositivos_gps']) && is_array($viaje['dispositivos_gps'])) {
                foreach ($viaje['dispositivos_gps'] as $gps) {
                    $trip->gpsDevices()->create([
                        'gps_provider' => $gps['proveedor'] ?? null,
                        'uri_gps'      => $gps['uri_gps'] ?? null,
                        'user'      => $gps['usuario'] ?? null,
                        'password'        => $gps['clave'] ?? null,
                    ]);
                }
            }

            $countImported++;
        }

        $this->info("Carga automática completada. Se importaron {$countImported} viajes.");
        return 0;
    }
}
