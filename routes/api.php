<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TripController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AuthenticateWithCookie;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me',    [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::post('/trips/import', [TripController::class, 'importExcel']);
        // Rutas para actualizar trips, registrar updates, etc.
    });


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/trips/template', [TripController::class, 'downloadTemplate']);
Route::get('/trips/template', [TripController::class, 'downloadTemplate'])->middleware(['auth:sanctum']);
Route::post('/trips/import', [TripController::class, 'importExcel'])->middleware(['auth:sanctum', 'role:Supervisor de proyecto,Administrador']);
