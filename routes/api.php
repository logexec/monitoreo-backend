<?php

use App\Http\Controllers\Api\GeotabController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TripController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardTripsSpecificInformation;
use App\Http\Controllers\Api\TripUpdatesController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/personnel', [TripController::class, 'getDriverName']);
Route::get('/plate-numbers', [TripController::class, 'getPlateNumbers']);

Route::get('/dashboardTrips', [DashboardTripsSpecificInformation::class, 'getMonthlyTrips']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me',    [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('geotab')->group(function () {
    // Rutas principales
    Route::get('/devices', [GeotabController::class, 'getAlerts']);
    Route::get('/alerts', [GeotabController::class, 'getAlerts']);

    // Rutas de diagnóstico para depuración
    Route::get('/test-connection', [GeotabController::class, 'testConnection']);
    Route::get('/available-types', [GeotabController::class, 'getAvailableTypes']);
});

Route::middleware(['auth:sanctum'])
    ->group(function () {
        // Rutas protegidas por auth

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::post('/trips/import', [TripController::class, 'importExcel']);

        // Route::get('/personnel', [TripController::class, 'getDriverName']);

        // Route::get('/plate-numbers', [TripController::class, 'getPlateNumbers']);

        Route::get('/trip-updates', [TripUpdatesController::class, 'index']);
        Route::post('/trip-updates', [TripUpdatesController::class, 'store']);
    });


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/trips/template', [TripController::class, 'downloadTemplate']);
Route::get('/trips/template', [TripController::class, 'downloadTemplate'])->middleware(['auth:sanctum']);
Route::post('/trips/import', [TripController::class, 'importExcel'])->middleware(['auth:sanctum', 'role:Supervisor de proyecto,Administrador']);
