<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;

class GeoTabService
{
    private string $baseUrl;
    private string $sessionId;
    private string $database;

    public function __construct()
    {
        $this->baseUrl = config('geotab.base_url', 'https://my.geotab.com/apiv1');
        $this->database = config('geotab.database');
        $this->sessionId = $this->authenticate();
    }

    private function authenticate(): string
    {
        try {
            $response = Http::post($this->baseUrl, [
                'method' => 'Authenticate',
                'params' => [
                    'userName' => config('geotab.username'),
                    'password' => config('geotab.password'),
                    'server' => config('geotab.server'),
                    'database' => $this->database,
                ]
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                throw new \Exception("GeoTab auth error: " . $data['error']['message']);
            }

            return $data['result']['credentials']['sessionId'] ?? '';
        } catch (\Exception $e) {
            Log::error('GeoTab auth failed', ['exception' => $e]);
            throw $e;
        }
    }

    public function call(string $method, array $params = [])
    {
        if (!$this->sessionId) {
            $this->sessionId = $this->authenticate();
        }

        // Añade los credentials obligatorios a cada request
        $params['credentials'] = [
            'database' => $this->database,
            'sessionId' => $this->sessionId,
            'userName' => config('geotab.username'),
            'server' => config('geotab.server'),
        ];

        $response = Http::post($this->baseUrl, [
            'method' => $method,
            'params' => $params,
        ]);

        $data = $response->json();
        Log::info("GeoTab [$method] response", $data);

        if (isset($data['error'])) {
            Log::error('GeoTab API error', ['error' => $data['error']]);
            throw new \Exception($data['error']['message']);
        }

        return $data['result'];
    }

    public function resolveDeviceIdByPlaca(string $placa): ?string
    {
        $vehiculo = DB::connection('tms1')
            ->table('vehiculos')
            ->where('name', $placa)
            ->first();
        // dd($vehiculo);

        return $vehiculo?->geotab_id ?? null;
    }
}
