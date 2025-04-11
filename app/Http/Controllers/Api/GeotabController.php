<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Geotab\API;
use Illuminate\Support\Facades\Log;

class GeotabController extends Controller
{
    private string $username;
    private string $password;
    private string $database;
    private string $server;
    private ?API $api = null;

    // Mapeo de diagnósticos conocidos
    private array $diagnosticMapping = [
        'EngineSpeed' => 'Revoluciones del motor (RPM)',
        'EngineRoadSpeed' => 'Velocidad del vehículo',
        'EngineCoolantTemperature' => 'Temperatura del refrigerante',
        'EngineOilPressure' => 'Presión del aceite del motor',
        'BatteryVoltage' => 'Voltaje de la batería',
        'EngineLoad' => 'Carga del motor',
        'FuelLevel' => 'Nivel de combustible',
        'ThrottlePosition' => 'Posición del acelerador',
        'EngineHours' => 'Horas de funcionamiento del motor',
        'AmbientAirTemperature' => 'Temperatura del aire ambiente',
        'IntakeAirTemperature' => 'Temperatura de admisión de aire',
        'BarometricPressure' => 'Presión barométrica',
        'TransmissionOilTemperature' => 'Temperatura del aceite de transmisión',
        'BrakeSwitch' => 'Interruptor de freno',
        'PTOStatus' => 'Estado de la Toma de Fuerza (PTO)',
        'Odometer' => 'Odómetro',
        'FuelUsed' => 'Combustible utilizado',
        'CruiseControlStatus' => 'Estado del control crucero',
        'Torque' => 'Torque del motor',
        'AccelerationX' => 'Aceleración (X)',
        'AccelerationY' => 'Aceleración (Y)',
        'AccelerationZ' => 'Aceleración (Z)',
        'AccidentLevelAccelerationEvent' => 'Evento de aceleración brusca (nivel de accidente)',
        'aeAx1iw7U28BQED9dJ7Fg' => 'Evento de excepción',
        'aipGZwHSihU2SLOhKLrjpwA' => 'Evento de alerta de conducción',
    ];

    // Unidades para valores de diagnóstico
    private array $diagnosticUnits = [
        'EngineSpeed' => 'RPM',
        'EngineRoadSpeed' => 'km/h',
        'EngineCoolantTemperature' => '°C',
        'EngineOilPressure' => 'kPa',
        'BatteryVoltage' => 'V',
        'EngineLoad' => '%',
        'FuelLevel' => '%',
        'ThrottlePosition' => '%',
        'EngineHours' => 'horas',
        'AmbientAirTemperature' => '°C',
        'IntakeAirTemperature' => '°C',
        'BarometricPressure' => 'kPa',
        'TransmissionOilTemperature' => '°C',
        'Odometer' => 'km',
        'FuelUsed' => 'L',
        'Torque' => 'Nm',
        'AccelerationX' => 'g',
        'AccelerationY' => 'g',
        'AccelerationZ' => 'g',
    ];

    // Mapeo de tipos de alertas
    private array $alertTypeMapping = [
        'ExceptionRuleHarshBraking' => 'hardBraking',
        'ExceptionRuleHarshCorner' => 'harshAcceleration',
        'ExceptionRuleIdle' => 'idling',
        'ExceptionRuleSpeeding' => 'speeding',
        'DiagnosticRuleFuelLevel' => 'fuelLevel',
        'ZoneRule' => 'geofence',
        'DiagnosticRule' => 'engineFault',
        'MaintenanceRule' => 'maintenance',
    ];

    // Palabras clave para detectar tipos de alertas
    private array $alertKeywords = [
        'speed' => 'speeding',
        'brake' => 'hardBraking',
        'accel' => 'harshAcceleration',
        'idle' => 'idling',
        'fuel' => 'fuelLevel',
        'zone' => 'geofence',
        'fault' => 'engineFault',
        'diagnos' => 'engineFault',
        'maint' => 'maintenance',
    ];

    // Rangos normales para valores de diagnóstico
    private array $normalRanges = [
        'EngineSpeed' => ['min' => 500, 'max' => 3000, 'alarmMin' => 400, 'alarmMax' => 5000],
        'EngineRoadSpeed' => ['min' => 0, 'max' => 120, 'alarmMin' => 0, 'alarmMax' => 160],
        'EngineCoolantTemperature' => ['min' => 80, 'max' => 95, 'alarmMin' => 60, 'alarmMax' => 110],
        'EngineOilPressure' => ['min' => 200, 'max' => 500, 'alarmMin' => 100, 'alarmMax' => 700],
        'BatteryVoltage' => ['min' => 12.5, 'max' => 14.5, 'alarmMin' => 11, 'alarmMax' => 15],
        'FuelLevel' => ['min' => 20, 'max' => 100, 'alarmMin' => 10, 'alarmMax' => 100],
    ];

    public function __construct()
    {
        $this->username = env('GEOTAB_USERNAME');
        $this->password = env('GEOTAB_PASSWORD');
        $this->database = env('GEOTAB_DATABASE');
        $this->server = env('GEOTAB_SERVER', 'my.geotab.com');
    }

    /**
     * Inicializar la API de Geotab
     */
    private function initApi()
    {
        if ($this->api === null) {
            $this->api = new API(
                $this->username,
                $this->password,
                $this->database,
                $this->server
            );
        }
        return $this->api;
    }

    /**
     * Obtener alertas de Geotab
     */
    public function getAlerts(Request $request)
    {
        try {
            // Inicializar la API
            $api = $this->initApi();
            $api->authenticate();

            // Determinar fechas para la consulta (últimas 24 horas por defecto)
            $fromDate = $request->input('fromDate') ?
                new \DateTime($request->input('fromDate')) :
                new \DateTime('24 hours ago');

            $toDate = $request->input('toDate') ?
                new \DateTime($request->input('toDate')) :
                new \DateTime();

            // Formatear fechas para la API de Geotab (ISO 8601)
            $fromDateFormatted = $fromDate->format('c');
            $toDateFormatted = $toDate->format('c');

            // Array para almacenar todas las alertas
            $alerts = [];

            // Basado en el ejemplo JavaScript, vamos a usar multiCall para obtener los datos de ubicación,
            // estado y fallos en una sola llamada
            $apiCalls = [];

            // 1. Obtener datos de ubicación (LogRecord)
            $apiCalls[] = [
                'GetFeed',
                [
                    'typeName' => 'LogRecord',
                    'fromVersion' => null,
                    'search' => [
                        'fromDate' => $fromDateFormatted
                    ],
                    'resultsLimit' => 1000,
                ]
            ];

            // 2. Obtener datos de estado (StatusData)
            $apiCalls[] = [
                'GetFeed',
                [
                    'typeName' => 'StatusData',
                    'fromVersion' => null,
                    'search' => [
                        'fromDate' => $fromDateFormatted
                    ],
                    'resultsLimit' => 1000,
                ]
            ];

            // 3. Obtener datos de fallos (FaultData)
            $apiCalls[] = [
                'GetFeed',
                [
                    'typeName' => 'FaultData',
                    'fromVersion' => null,
                    'search' => [
                        'fromDate' => $fromDateFormatted
                    ],
                    'resultsLimit' => 1000,
                ]
            ];

            // Información de ubicación por dispositivo para relacionarla con alertas
            $locationByDevice = [];
            $speedByDevice = [];

            // Realizar la llamada múltiple
            $multiResults = [];
            $api->multiCall($apiCalls, function ($results) use (&$multiResults) {
                $multiResults = $results;
            }, function ($error) {
                Log::error("Error en multiCall: " . json_encode($error));
            });

            // Primero, obtener datos de diagnóstico para enriquecer las alertas
            $diagnostics = [];
            $api->get('Diagnostic', [
                'resultsLimit' => 500
            ], function ($results) use (&$diagnostics) {
                if (is_array($results)) {
                    foreach ($results as $diagnostic) {
                        if (isset($diagnostic['id'])) {
                            // Limpiar el ID para que coincida con el formato que recibimos en los datos
                            $cleanId = str_replace(['DiagnosticId', 'Diagnostic'], '', $diagnostic['id']);
                            $diagnostics[$cleanId] = $diagnostic;

                            // También almacenar con el ID completo por si acaso
                            $diagnostics[$diagnostic['id']] = $diagnostic;
                        }
                    }
                }
            }, function ($error) {
                Log::error("Error al obtener Diagnostic: " . json_encode($error));
            });

            // Para cada diagnóstico, también obtener información sobre sus unidades y valores normales
            foreach ($diagnostics as $id => $diagnostic) {
                if (isset($diagnostic['unitOfMeasure']) && !empty($diagnostic['unitOfMeasure'])) {
                    $this->diagnosticUnits[$id] = $diagnostic['unitOfMeasure'];
                }
            }

            // Procesar resultados de la multiCall
            if (!empty($multiResults)) {
                foreach ($multiResults as $index => $result) {
                    // Verificar que el resultado tenga datos
                    if (isset($result['data']) && is_array($result['data'])) {
                        // Procesar según el tipo de datos
                        if ($index === 0) { // LogRecord (ubicación)
                            foreach ($result['data'] as $logRecord) {
                                if (
                                    isset($logRecord['device']['id']) &&
                                    isset($logRecord['latitude']) &&
                                    isset($logRecord['longitude'])
                                ) {

                                    $deviceId = $logRecord['device']['id'];
                                    // Almacenar la ubicación más reciente por dispositivo
                                    if (
                                        !isset($locationByDevice[$deviceId]) ||
                                        (isset($logRecord['dateTime']) &&
                                            $logRecord['dateTime'] > $locationByDevice[$deviceId]['dateTime'])
                                    ) {

                                        $locationByDevice[$deviceId] = [
                                            'latitude' => $logRecord['latitude'],
                                            'longitude' => $logRecord['longitude'],
                                            'dateTime' => $logRecord['dateTime'] ?? '',
                                            'address' => $this->getAddressFromCoordinates($logRecord['latitude'], $logRecord['longitude'])
                                        ];
                                    }

                                    // Almacenar la velocidad más reciente por dispositivo
                                    if (isset($logRecord['speed'])) {
                                        if (
                                            !isset($speedByDevice[$deviceId]) ||
                                            (isset($logRecord['dateTime']) &&
                                                $logRecord['dateTime'] > $speedByDevice[$deviceId]['dateTime'])
                                        ) {

                                            $speedByDevice[$deviceId] = [
                                                'speed' => $logRecord['speed'],
                                                'dateTime' => $logRecord['dateTime'] ?? ''
                                            ];
                                        }
                                    }

                                    // Si queremos registrar todos los datos de ubicación como alertas
                                    if ($request->input('includeAllLocations', false)) {
                                        $alertType = 'location';
                                        $severity = 'low';
                                        $description = 'Registro de ubicación';

                                        // Si hay velocidad, verificar si es alta
                                        if (isset($logRecord['speed']) && $logRecord['speed'] > 80) {
                                            $alertType = 'speeding';
                                            $severity = $logRecord['speed'] > 100 ? 'high' : 'medium';
                                            $description = "Exceso de velocidad: {$logRecord['speed']} km/h";
                                        }

                                        $alert = [
                                            'id' => $logRecord['id'] ?? uniqid('loc_'),
                                            'type' => $alertType,
                                            'severity' => $severity,
                                            'timestamp' => $logRecord['dateTime'] ?? date('c'),
                                            'activeFrom' => $logRecord['dateTime'] ?? date('c'),
                                            'device' => $logRecord['device'] ?? null,
                                            'description' => $description,
                                            'isActive' => true,
                                            'resolved' => false,
                                            'location' => [
                                                'address' => $this->getAddressFromCoordinates($logRecord['latitude'], $logRecord['longitude']),
                                                'latitude' => $logRecord['latitude'],
                                                'longitude' => $logRecord['longitude']
                                            ],
                                            'speed' => $logRecord['speed'] ?? null
                                        ];

                                        $alerts[] = $alert;
                                    }
                                }
                            }
                        } else if ($index === 1) { // StatusData
                            foreach ($result['data'] as $statusRecord) {
                                if (isset($statusRecord['diagnostic']) && isset($statusRecord['data'])) {
                                    // Solo procesar registros con diagnóstico y datos relevantes
                                    if ($statusRecord['data'] != '0') {
                                        $deviceId = $statusRecord['device']['id'] ?? '';

                                        // Extraer el ID del diagnóstico limpio
                                        $diagnosticId = '';
                                        $originalDiagnosticId = '';
                                        if (isset($statusRecord['diagnostic']['id'])) {
                                            $originalDiagnosticId = $statusRecord['diagnostic']['id'];
                                            $diagnosticId = str_replace(['Diagnostic', 'Id'], '', $originalDiagnosticId);
                                        }

                                        // Obtener el nombre del diagnóstico
                                        $diagnosticName = $this->getDiagnosticName($diagnosticId, $diagnostics, $statusRecord['diagnostic']['name'] ?? null);

                                        // Formatear valor del diagnóstico
                                        $diagnosticValue = $statusRecord['data'];
                                        $formattedValue = $this->formatDiagnosticValue($diagnosticId, $diagnosticValue);

                                        // Generar una descripción más informativa
                                        $description = "Diagnóstico: $diagnosticName";
                                        if ($formattedValue) {
                                            $description .= " - $formattedValue";
                                        }

                                        // Determinar tipo de alerta
                                        $alertType = $this->getDiagnosticType($diagnosticId, $diagnosticName);

                                        // Determinar severidad
                                        $severity = $this->getDiagnosticSeverity($diagnosticId, $diagnosticValue, $diagnosticName);

                                        // Para diagnósticos específicos, personalizar la descripción
                                        if (
                                            strpos(strtolower($diagnosticId), 'enginespeed') !== false ||
                                            strpos(strtolower($diagnosticName), 'revoluciones') !== false ||
                                            strpos(strtolower($diagnosticName), 'rpm') !== false
                                        ) {

                                            if ($diagnosticValue > 3000) {
                                                $description = "¡Revoluciones del motor muy altas! - $formattedValue";
                                                $alertType = 'engineFault';
                                                $severity = 'high';
                                            }
                                        }

                                        if (
                                            strpos(strtolower($diagnosticId), 'roadspeed') !== false ||
                                            strpos(strtolower($diagnosticName), 'velocidad') !== false
                                        ) {

                                            if ($diagnosticValue > 0 && $diagnosticValue <= 10) {
                                                $description = "Vehículo en movimiento lento - $formattedValue";
                                                $alertType = 'speeding';
                                                $severity = 'low';
                                            } else if ($diagnosticValue > 80 && $diagnosticValue <= 100) {
                                                $description = "Velocidad elevada - $formattedValue";
                                                $alertType = 'speeding';
                                                $severity = 'medium';
                                            } else if ($diagnosticValue > 100) {
                                                $description = "¡Exceso de velocidad! - $formattedValue";
                                                $alertType = 'speeding';
                                                $severity = 'high';
                                            }
                                        }

                                        $alert = [
                                            'id' => $statusRecord['id'] ?? uniqid('status_'),
                                            'type' => $alertType,
                                            'severity' => $severity,
                                            'timestamp' => $statusRecord['dateTime'] ?? date('c'),
                                            'activeFrom' => $statusRecord['dateTime'] ?? date('c'),
                                            'device' => $statusRecord['device'] ?? null,
                                            'description' => $description,
                                            'isActive' => true,
                                            'resolved' => false,
                                            'diagnosticId' => $diagnosticId,
                                            'diagnosticValue' => $diagnosticValue
                                        ];

                                        // Añadir ubicación si existe para este dispositivo
                                        if (!empty($deviceId) && isset($locationByDevice[$deviceId])) {
                                            $alert['location'] = [
                                                'address' => $locationByDevice[$deviceId]['address'],
                                                'latitude' => $locationByDevice[$deviceId]['latitude'],
                                                'longitude' => $locationByDevice[$deviceId]['longitude']
                                            ];
                                        } else {
                                            $alert['location'] = [
                                                'address' => 'Ubicación no disponible',
                                                'latitude' => 0,
                                                'longitude' => 0
                                            ];
                                        }

                                        // Añadir velocidad si existe para este dispositivo
                                        if (!empty($deviceId) && isset($speedByDevice[$deviceId])) {
                                            $alert['speed'] = $speedByDevice[$deviceId]['speed'];
                                        }

                                        $alerts[] = $alert;
                                    }
                                }
                            }
                        } else if ($index === 2) { // FaultData
                            foreach ($result['data'] as $faultRecord) {
                                if (isset($faultRecord['failureMode'])) {
                                    $deviceId = $faultRecord['device']['id'] ?? '';
                                    $faultName = $faultRecord['failureMode']['name'] ?? 'Fallo desconocido';
                                    $faultCode = $faultRecord['failureMode']['code'] ?? 'N/A';

                                    // Intentar extraer un ID del fallo más significativo
                                    $faultId = $faultRecord['failureMode']['id'] ?? 'unknown';
                                    if ($faultId === 'unknown' && isset($faultRecord['diagnostic']['id'])) {
                                        $faultId = str_replace(['Diagnostic', 'Id'], '', $faultRecord['diagnostic']['id']);
                                    }

                                    // Limpiar nombre del fallo
                                    if ($faultName === 'Fallo desconocido' && $faultId !== 'unknown') {
                                        $faultName = $this->getDiagnosticName($faultId, $diagnostics, $faultName);
                                    }

                                    // Generar mejor descripción
                                    $description = "Fallo: $faultName";
                                    if ($faultCode !== 'N/A' && $faultCode !== null && $faultCode !== '') {
                                        $description .= " (Código: $faultCode)";
                                    }

                                    $alert = [
                                        'id' => $faultRecord['id'] ?? uniqid('fault_'),
                                        'type' => 'engineFault',
                                        'severity' => 'high', // Los fallos suelen ser más severos
                                        'timestamp' => $faultRecord['dateTime'] ?? date('c'),
                                        'activeFrom' => $faultRecord['dateTime'] ?? date('c'),
                                        'device' => $faultRecord['device'] ?? null,
                                        'description' => $description,
                                        'isActive' => true,
                                        'resolved' => false,
                                        'diagnosticId' => $faultId,
                                        'diagnosticValue' => $faultCode
                                    ];

                                    // Añadir ubicación si existe para este dispositivo
                                    if (!empty($deviceId) && isset($locationByDevice[$deviceId])) {
                                        $alert['location'] = [
                                            'address' => $locationByDevice[$deviceId]['address'],
                                            'latitude' => $locationByDevice[$deviceId]['latitude'],
                                            'longitude' => $locationByDevice[$deviceId]['longitude']
                                        ];
                                    } else {
                                        $alert['location'] = [
                                            'address' => 'Ubicación no disponible',
                                            'latitude' => 0,
                                            'longitude' => 0
                                        ];
                                    }

                                    // Añadir velocidad si existe para este dispositivo
                                    if (!empty($deviceId) && isset($speedByDevice[$deviceId])) {
                                        $alert['speed'] = $speedByDevice[$deviceId]['speed'];
                                    }

                                    $alerts[] = $alert;
                                }
                            }
                        }
                    }
                }
            }

            // 4. Obtener notificaciones de reglas (no incluidas en multiCall)
            $ruleNotifications = [];
            $api->get('RuleNotification', [
                'resultsLimit' => 100,
                'search' => [
                    'fromDate' => $fromDateFormatted
                ]
            ], function ($results) use (&$ruleNotifications) {
                $ruleNotifications = $results ?: [];
            }, function ($error) {
                Log::error("Error al obtener RuleNotification: " . json_encode($error));
            });

            foreach ($ruleNotifications as $notification) {
                $deviceId = $notification['device']['id'] ?? '';

                // Convertir notificaciones a formato de alerta
                $alert = [
                    'id' => $notification['id'] ?? uniqid('not_'),
                    'type' => $this->mapRuleTypeToAlertType($notification['rule']['ruleType'] ?? 'unknown'),
                    'severity' => $this->determineSeverityFromRule($notification['rule'] ?? []),
                    'timestamp' => $notification['activeFrom'] ?? date('c'),
                    'activeFrom' => $notification['activeFrom'] ?? date('c'),
                    'activeTo' => $notification['activeTo'] ?? null,
                    'device' => $notification['device'] ?? null,
                    'rule' => $notification['rule'] ?? null,
                    'description' => $notification['rule']['name'] ?? 'Alerta del sistema',
                    'isActive' => !isset($notification['activeTo']),
                    'resolved' => isset($notification['activeTo'])
                ];

                // Añadir ubicación si existe para este dispositivo
                if (!empty($deviceId) && isset($locationByDevice[$deviceId])) {
                    $alert['location'] = [
                        'address' => $locationByDevice[$deviceId]['address'],
                        'latitude' => $locationByDevice[$deviceId]['latitude'],
                        'longitude' => $locationByDevice[$deviceId]['longitude']
                    ];
                } else {
                    $alert['location'] = [
                        'address' => 'Ubicación no disponible',
                        'latitude' => 0,
                        'longitude' => 0
                    ];
                }

                // Añadir velocidad si existe para este dispositivo
                if (!empty($deviceId) && isset($speedByDevice[$deviceId])) {
                    $alert['speed'] = $speedByDevice[$deviceId]['speed'];
                }

                $alerts[] = $alert;
            }

            // 5. También obtener eventos de excepción para tener más alertas
            $exceptionEvents = [];
            $api->call('GetFeed', [
                'typeName' => 'ExceptionEvent',
                'fromVersion' => null,
                'resultsLimit' => 100,
                'search' => [
                    'fromDate' => $fromDateFormatted,
                    'toDate' => $toDateFormatted
                ]
            ], function ($results) use (&$exceptionEvents) {
                if (isset($results['data']) && is_array($results['data'])) {
                    $exceptionEvents = $results['data'];
                } else {
                    $exceptionEvents = [];
                }
            }, function ($error) {
                Log::error("Error al obtener ExceptionEvent: " . json_encode($error));
            });

            foreach ($exceptionEvents as $event) {
                // Aquí los eventos de excepción ya suelen incluir latitud, longitud y velocidad
                $alert = [
                    'id' => $event['id'] ?? uniqid('evt_'),
                    'type' => $this->mapExceptionTypeToAlertType($event['rule']['ruleType'] ?? 'unknown'),
                    'severity' => $this->determineSeverity($event),
                    'timestamp' => $event['activeFrom'] ?? $event['dateTime'] ?? date('c'),
                    'activeFrom' => $event['activeFrom'] ?? $event['dateTime'] ?? date('c'),
                    'activeTo' => $event['activeTo'] ?? null,
                    'device' => $event['device'] ?? null,
                    'rule' => $event['rule'] ?? null,
                    'description' => $event['rule']['name'] ?? 'Alerta del sistema',
                    'isActive' => !isset($event['activeTo']),
                    'resolved' => isset($event['activeTo']),
                    'location' => [
                        'address' => $this->getAddressFromCoordinates($event['latitude'] ?? 0, $event['longitude'] ?? 0),
                        'latitude' => $event['latitude'] ?? 0,
                        'longitude' => $event['longitude'] ?? 0
                    ]
                ];

                if (isset($event['speed'])) {
                    $alert['speed'] = $event['speed'];
                } else {
                    // Si no tiene velocidad, intentar obtenerla del dispositivo
                    $deviceId = $event['device']['id'] ?? '';
                    if (!empty($deviceId) && isset($speedByDevice[$deviceId])) {
                        $alert['speed'] = $speedByDevice[$deviceId]['speed'];
                    }
                }

                $alerts[] = $alert;
            }

            // Obtener información de dispositivos para enriquecer los datos
            $deviceIds = [];
            foreach ($alerts as $alert) {
                if (isset($alert['device']['id'])) {
                    $deviceIds[] = $alert['device']['id'];
                }
            }

            $deviceIds = array_unique($deviceIds);
            $devices = [];

            if (!empty($deviceIds)) {
                $api->get('Device', [
                    'search' => [
                        'id' => $deviceIds
                    ]
                ], function ($results) use (&$devices) {
                    if (is_array($results)) {
                        foreach ($results as $device) {
                            $devices[$device['id']] = $device;
                        }
                    }
                }, function ($error) {
                    Log::error("Error al obtener Device: " . json_encode($error));
                });
            }

            // Obtener conductores
            $drivers = [];
            $api->get('Driver', [
                'resultsLimit' => 100
            ], function ($results) use (&$drivers) {
                if (is_array($results)) {
                    foreach ($results as $driver) {
                        $drivers[$driver['id']] = $driver;
                    }
                }
            }, function ($error) {
                Log::error("Error al obtener Driver: " . json_encode($error));
            });

            // Enriquecer alertas con información de dispositivos y conductores
            foreach ($alerts as &$alert) {
                if (isset($alert['device']['id']) && isset($devices[$alert['device']['id']])) {
                    $deviceId = $alert['device']['id'];
                    $alert['deviceInfo'] = $devices[$deviceId];
                    $alert['vehicleName'] = $devices[$deviceId]['name'] ?? 'Vehículo';

                    // Intentar encontrar el conductor asignado a este vehículo
                    foreach ($drivers as $driver) {
                        if (isset($driver['groups']) && is_array($driver['groups'])) {
                            foreach ($driver['groups'] as $group) {
                                if (isset($group['id']) && $group['id'] === $deviceId) {
                                    $alert['driverId'] = $driver['id'];
                                    $alert['driverName'] = $driver['name'];
                                    break 2;
                                }
                            }
                        }
                    }
                }

                // Si no se encontró conductor, establecer uno por defecto
                if (!isset($alert['driverName'])) {
                    $alert['driverName'] = 'No asignado';
                }

                // Asegurarse de que haya una descripción
                if (empty($alert['description'])) {
                    $deviceName = $alert['vehicleName'] ?? 'Vehículo';
                    $ruleName = isset($alert['rule']['name']) ? $alert['rule']['name'] : 'Alerta';
                    $alert['description'] = "$ruleName en $deviceName";
                }

                // Mejorar la descripción de las alertas con diagnósticos extraños
                if (isset($alert['diagnosticId']) && isset($alert['diagnosticValue'])) {
                    $diagnosticId = $alert['diagnosticId'];
                    $value = $alert['diagnosticValue'];

                    // Manejar casos específicos conocidos
                    if ($diagnosticId === 'AccidentLevelAccelerationEvent') {
                        $alert['description'] = "¡Alerta! Aceleración brusca de nivel de accidente detectada";
                        $alert['type'] = 'harshAcceleration';
                        $alert['severity'] = 'high';
                    } else if ($diagnosticId === 'aipGZwHSihU2SLOhKLrjpwA') {
                        $alert['description'] = "¡Alerta! Posible incidente de conducción detectado";
                        $alert['type'] = 'engineFault';
                        $alert['severity'] = 'high';
                    } else if ($diagnosticId === 'aeAx1iw7U28BQED9dJ7Fg') {
                        $alert['description'] = "¡Alerta! Evento de excepción de conducción detectado";
                        $alert['type'] = 'engineFault';
                        $alert['severity'] = 'high';
                    }
                    // Si tiene un ID de diagnóstico extraño (parece un hash o código aleatorio)
                    else if (strlen($diagnosticId) > 20 && !isset($this->diagnosticMapping[$diagnosticId])) {
                        // Si la descripción es genérica, intentar mejorarla
                        if (strpos($alert['description'], 'desconocido') !== false || $alert['description'] === 'Fallo detectado en el motor') {
                            if ($alert['type'] === 'speeding') {
                                $alert['description'] = "Alerta de velocidad" . ($value ? " - $value km/h" : "");
                            } elseif ($alert['type'] === 'hardBraking') {
                                $alert['description'] = "Frenado brusco detectado";
                            } elseif ($alert['type'] === 'harshAcceleration') {
                                $alert['description'] = "Aceleración brusca detectada";
                            } elseif ($alert['type'] === 'engineFault') {
                                $alert['description'] = "Alerta del sistema detectada en el vehículo";
                            }
                        }
                    }
                }
            }

            // Eliminar alertas duplicadas basadas en ID
            $uniqueAlerts = [];
            $alertIds = [];
            foreach ($alerts as $alert) {
                if (!in_array($alert['id'], $alertIds)) {
                    $alertIds[] = $alert['id'];
                    $uniqueAlerts[] = $alert;
                }
            }

            // Registrar el número de alertas que enviamos
            Log::info('Alertas encontradas: ' . count($uniqueAlerts));

            return response()->json([
                'success' => true,
                'alerts' => $uniqueAlerts,
                'count' => count($uniqueAlerts)
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getAlerts: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Formatear el valor de un diagnóstico con sus unidades correspondientes
     */
    private function formatDiagnosticValue(string $diagnosticId, $value)
    {
        // Si el valor no está definido o está vacío
        if ($value === null || $value === '' || $value === 'N/A') {
            return null;
        }

        // Redondear números con muchos decimales
        if (is_numeric($value)) {
            if (abs($value) >= 100) {
                $value = round($value);
            } else if (abs($value) >= 10) {
                $value = round($value, 1);
            } else {
                $value = round($value, 2);
            }
        }

        // Añadir unidades si las conocemos
        if (isset($this->diagnosticUnits[$diagnosticId])) {
            return "$value {$this->diagnosticUnits[$diagnosticId]}";
        }

        // Intentar inferir unidades por el ID
        $lowerDiagId = strtolower($diagnosticId);

        if (strpos($lowerDiagId, 'speed') !== false) {
            return "$value km/h";
        }

        if (strpos($lowerDiagId, 'temp') !== false) {
            return "$value °C";
        }

        if (strpos($lowerDiagId, 'fuel') !== false && strpos($lowerDiagId, 'level') !== false) {
            return "$value %";
        }

        if (strpos($lowerDiagId, 'battery') !== false || strpos($lowerDiagId, 'volt') !== false) {
            return "$value V";
        }

        if (strpos($lowerDiagId, 'pressure') !== false) {
            return "$value kPa";
        }

        // Si no tenemos unidades específicas, devolver solo el valor
        return "$value";
    }

    /**
     * Obtener el nombre de un diagnóstico en base a su ID
     */
    private function getDiagnosticName(string $diagnosticId, array $diagnostics, ?string $fallbackName = null): string
    {
        // 1. Comprobar en nuestro mapeo local
        if (isset($this->diagnosticMapping[$diagnosticId])) {
            return $this->diagnosticMapping[$diagnosticId];
        }

        // 2. Comprobar en los diagnósticos obtenidos de la API
        if (isset($diagnostics[$diagnosticId]) && isset($diagnostics[$diagnosticId]['name'])) {
            return $diagnostics[$diagnosticId]['name'];
        }

        // 3. Si tenemos un nombre de respaldo válido
        if (!empty($fallbackName) && $fallbackName !== 'Diagnóstico desconocido') {
            return $fallbackName;
        }

        // 4. Intentar interpretar el ID para generar un nombre más amigable
        if (!empty($diagnosticId) && $diagnosticId !== 'unknown' && strlen($diagnosticId) < 20) {
            // Dividir por mayúsculas (camelCase)
            $words = preg_split('/(?=[A-Z])/', $diagnosticId);
            if (!empty($words)) {
                $words = array_filter($words); // Eliminar elementos vacíos
                return implode(' ', $words);
            }
        }

        // 5. Si el ID es muy largo (parece un hash), no incluirlo en la descripción
        if (strlen($diagnosticId) >= 20) {
            return "Parámetro del vehículo";
        }

        // 6. Último recurso
        return !empty($diagnosticId) ? "Parámetro: $diagnosticId" : "Diagnóstico desconocido";
    }

    /**
     * Determinar el tipo de alerta basado en el ID de diagnóstico
     */
    private function getDiagnosticType(string $diagnosticId, string $diagnosticName = ''): string
    {
        $diagnosticId = strtolower($diagnosticId);
        $diagnosticName = strtolower($diagnosticName);

        // Casos especiales
        if (strpos($diagnosticId, 'accident') !== false) {
            return 'harshAcceleration';
        }

        // Comprobar por ID
        if (strpos($diagnosticId, 'speed') !== false) {
            return 'speeding';
        }

        if (strpos($diagnosticId, 'fuel') !== false) {
            return 'fuelLevel';
        }

        if (strpos($diagnosticId, 'brake') !== false) {
            return 'hardBraking';
        }

        if (strpos($diagnosticId, 'accel') !== false) {
            return 'harshAcceleration';
        }

        if (strpos($diagnosticId, 'idle') !== false) {
            return 'idling';
        }

        if (strpos($diagnosticId, 'maint') !== false) {
            return 'maintenance';
        }

        // Comprobar por nombre
        if (strpos($diagnosticName, 'velocidad') !== false || strpos($diagnosticName, 'speed') !== false) {
            return 'speeding';
        }

        if (strpos($diagnosticName, 'combustible') !== false || strpos($diagnosticName, 'fuel') !== false) {
            return 'fuelLevel';
        }

        if (strpos($diagnosticName, 'freno') !== false || strpos($diagnosticName, 'brake') !== false) {
            return 'hardBraking';
        }

        if (strpos($diagnosticName, 'aceler') !== false) {
            return 'harshAcceleration';
        }

        if (strpos($diagnosticName, 'motor') !== false || strpos($diagnosticName, 'engine') !== false) {
            return 'engineFault';
        }

        // Por defecto, consideramos que es un fallo del motor
        return 'engineFault';
    }

    /**
     * Determinar la severidad basado en el ID de diagnóstico y su valor
     */
    private function getDiagnosticSeverity(string $diagnosticId, $value, string $diagnosticName = ''): string
    {
        $lowerDiagId = strtolower($diagnosticId);
        $lowerDiagName = strtolower($diagnosticName);

        // Si no tenemos un valor válido, considerar severidad media
        if ($value === null || $value === '' || $value === 'N/A') {
            return 'medium';
        }

        // Intentar convertir a número para comparaciones
        if (!is_numeric($value)) {
            // Si no podemos convertir, probablemente sea un estado (on/off, etc.)
            if (is_string($value) && (
                strpos($value, 'crit') !== false ||
                strpos($value, 'high') !== false ||
                strpos($value, 'alt') !== false ||
                strpos($value, 'fail') !== false ||
                strpos($value, 'err') !== false
            )) {
                return 'high';
            }
            return 'medium';
        }

        // Ahora tenemos un valor numérico para comparaciones
        $numValue = (float) $value;

        // Casos específicos para diferentes tipos de diagnósticos

        // Temperatura del motor
        if ((strpos($lowerDiagId, 'engine') !== false && strpos($lowerDiagId, 'temp') !== false) ||
            (strpos($lowerDiagName, 'motor') !== false && strpos($lowerDiagName, 'temp') !== false) ||
            (strpos($lowerDiagName, 'refrig') !== false)
        ) {

            if ($numValue > 110) return 'critical';
            if ($numValue > 100) return 'high';
            if ($numValue > 95) return 'medium';
            if ($numValue < 60) return 'medium'; // Temperatura baja también puede ser problema
            return 'low';
        }

        // Voltaje de batería
        if (
            strpos($lowerDiagId, 'battery') !== false || strpos($lowerDiagId, 'volt') !== false ||
            strpos($lowerDiagName, 'bater') !== false || strpos($lowerDiagName, 'volt') !== false
        ) {

            if ($numValue < 10) return 'critical';
            if ($numValue < 11.5) return 'high';
            if ($numValue < 12) return 'medium';
            if ($numValue > 15) return 'high'; // Sobrevoltaje también es problema
            return 'low';
        }

        // Nivel de combustible
        if ((strpos($lowerDiagId, 'fuel') !== false && strpos($lowerDiagId, 'level') !== false) ||
            (strpos($lowerDiagName, 'combust') !== false && strpos($lowerDiagName, 'nivel') !== false)
        ) {

            if ($numValue < 10) return 'critical';
            if ($numValue < 15) return 'high';
            if ($numValue < 25) return 'medium';
            return 'low';
        }

        // Presión de aceite
        if ((strpos($lowerDiagId, 'oil') !== false && strpos($lowerDiagId, 'pressure') !== false) ||
            (strpos($lowerDiagName, 'aceite') !== false && strpos($lowerDiagName, 'pres') !== false)
        ) {

            if ($numValue < 100) return 'critical';
            if ($numValue < 200) return 'high';
            return 'medium';
        }

        // Velocidad
        if (strpos($lowerDiagId, 'speed') !== false || strpos($lowerDiagName, 'velocidad') !== false) {
            if ($numValue > 130) return 'critical';
            if ($numValue > 100) return 'high';
            if ($numValue > 80) return 'medium';
            return 'low';
        }

        // RPM
        if (
            strpos($lowerDiagId, 'enginespeed') !== false || strpos($lowerDiagName, 'rpm') !== false ||
            strpos($lowerDiagName, 'revoluciones') !== false
        ) {

            if ($numValue > 5000) return 'critical';
            if ($numValue > 4000) return 'high';
            if ($numValue > 3000) return 'medium';
            return 'low';
        }

        // Por defecto, establecer severidad basada en el rango del 0-100%
        // Asumimos que valores muy altos o muy bajos pueden indicar problemas
        if ($numValue < 5 || $numValue > 95) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Obtener dirección a partir de coordenadas (geocodificación inversa)
     * En una implementación real, podrías usar un servicio como Google Maps
     */
    private function getAddressFromCoordinates($latitude, $longitude)
    {
        // Simplificación para el ejemplo
        if ($latitude == 0 && $longitude == 0) {
            return "Ubicación no disponible";
        }

        return "Coordenadas: $latitude, $longitude";

        // Para implementación real con Google Maps o similar:
        // $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=TU_API_KEY";
        // $response = file_get_contents($url);
        // $data = json_decode($response, true);
        // return $data['results'][0]['formatted_address'] ?? "Ubicación no disponible";
    }

    /**
     * Probar la conexión con Geotab
     */
    public function testConnection(Request $request)
    {
        try {
            // Inicializar la API
            $api = $this->initApi();
            $api->authenticate();

            // Obtener versión del servidor
            $version = '';
            $api->call('GetVersion', [], function ($result) use (&$version) {
                $version = $result;
            }, function ($error) {
                Log::error("Error al obtener versión: " . json_encode($error));
            });

            // Obtener información del usuario
            $userInfo = [];
            $api->call('GetUserInfo', [], function ($result) use (&$userInfo) {
                $userInfo = $result;
            }, function ($error) {
                Log::error("Error al obtener información de usuario: " . json_encode($error));
            });

            // Obtener dispositivos
            $devices = [];
            $api->get('Device', [
                'resultsLimit' => 5
            ], function ($results) use (&$devices) {
                $devices = $results ?: [];
            }, function ($error) {
                Log::error("Error al obtener dispositivos: " . json_encode($error));
            });

            return response()->json([
                'success' => true,
                'message' => 'Conexión con Geotab establecida correctamente',
                'data' => [
                    'auth' => [
                        'database' => $this->database,
                        'username' => $this->username,
                        'server' => $this->server
                    ],
                    'version' => $version,
                    'user' => $userInfo,
                    'devices' => [
                        'count' => count($devices),
                        'sample' => array_slice($devices, 0, 2)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en test de conexión Geotab: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con Geotab',
                'error' => $e->getMessage(),
                'details' => [
                    'server' => $this->server,
                    'database' => $this->database,
                    'username' => $this->username
                ]
            ], 500);
        }
    }

    /**
     * Obtener tipos de datos disponibles
     */
    public function getAvailableTypes(Request $request)
    {
        try {
            // Inicializar la API
            $api = $this->initApi();
            $api->authenticate();

            // Llamar a GetDataTypes
            $types = [];
            $api->call('GetDataTypes', [], function ($result) use (&$types) {
                $types = $result ?: [];
            }, function ($error) {
                Log::error("Error al obtener tipos de datos: " . json_encode($error));
            });

            // Organizar por categorías
            $categories = [];
            foreach ($types as $type) {
                if (!isset($type['type']) || !isset($type['name'])) {
                    continue;
                }

                $category = explode('.', $type['type']);
                $category = end($category);

                if (!isset($categories[$category])) {
                    $categories[$category] = [];
                }

                $categories[$category][] = [
                    'name' => $type['name'],
                    'type' => $type['type']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'allTypes' => $types,
                    'byCategory' => $categories
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mapear tipo de excepción a tipo de alerta
     */
    private function mapExceptionTypeToAlertType($type)
    {
        // Usar el mapeo definido como propiedad de la clase
        if (isset($this->alertTypeMapping[$type])) {
            return $this->alertTypeMapping[$type];
        }

        // Si el tipo contiene alguna de estas palabras clave
        $lowercaseType = strtolower($type);
        foreach ($this->alertKeywords as $keyword => $alertType) {
            if (strpos($lowercaseType, $keyword) !== false) {
                return $alertType;
            }
        }

        return 'engineFault'; // Valor por defecto
    }

    /**
     * Mapear tipo de regla a tipo de alerta
     */
    private function mapRuleTypeToAlertType($ruleType)
    {
        return $this->mapExceptionTypeToAlertType($ruleType);
    }

    /**
     * Determinar la severidad en base al evento
     */
    private function determineSeverity($event)
    {
        // Si hay información explícita de severidad, usarla
        if (isset($event['severity'])) {
            $severityValue = strtolower($event['severity']);
            if (in_array($severityValue, ['low', 'medium', 'high', 'critical'])) {
                return $severityValue;
            }
        }

        // Basado en el tipo de regla
        if (isset($event['rule']['ruleType'])) {
            $ruleType = $event['rule']['ruleType'];

            if (strpos($ruleType, 'Speeding') !== false) {
                return 'high';
            }

            if (strpos($ruleType, 'Harsh') !== false || strpos($ruleType, 'Brake') !== false) {
                return 'medium';
            }

            if (strpos($ruleType, 'Idle') !== false) {
                return 'low';
            }

            if (strpos($ruleType, 'Diagnostic') !== false || strpos($ruleType, 'Fault') !== false) {
                return 'critical';
            }
        }

        // Basado en el nombre de la regla
        if (isset($event['rule']['name'])) {
            $ruleName = strtolower($event['rule']['name']);

            if (strpos($ruleName, 'crític') !== false || strpos($ruleName, 'grave') !== false) {
                return 'critical';
            }

            if (strpos($ruleName, 'alt') !== false || strpos($ruleName, 'sever') !== false) {
                return 'high';
            }

            if (strpos($ruleName, 'advertenc') !== false || strpos($ruleName, 'warn') !== false) {
                return 'medium';
            }
        }

        return 'medium';
    }

    /**
     * Determinar la severidad en base a la regla
     */
    private function determineSeverityFromRule($rule)
    {
        if (isset($rule['ruleType'])) {
            $ruleType = $rule['ruleType'];

            if (strpos($ruleType, 'Speeding') !== false) {
                return 'high';
            }

            if (strpos($ruleType, 'Harsh') !== false || strpos($ruleType, 'Brake') !== false) {
                return 'medium';
            }

            if (strpos($ruleType, 'Idle') !== false) {
                return 'low';
            }

            if (strpos($ruleType, 'Diagnostic') !== false || strpos($ruleType, 'Fault') !== false) {
                return 'critical';
            }
        }

        if (isset($rule['name'])) {
            $ruleName = strtolower($rule['name']);

            if (strpos($ruleName, 'crític') !== false || strpos($ruleName, 'grave') !== false) {
                return 'critical';
            }

            if (strpos($ruleName, 'alt') !== false || strpos($ruleName, 'sever') !== false) {
                return 'high';
            }

            if (strpos($ruleName, 'advertenc') !== false || strpos($ruleName, 'warn') !== false) {
                return 'medium';
            }
        }

        return 'medium';
    }
}
