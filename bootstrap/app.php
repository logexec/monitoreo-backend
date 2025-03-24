<?php

use App\Http\Middleware\AuthenticateWithCookie;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->append(RoleMiddleware::class); //Middleware Global
        $middleware->statefulApi(); //Middleware de sanctum para CSRF
        $middleware->validateCsrfTokens(except: [
            'api/login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function ($scheduler) {
        $scheduler->command('viajes:cargar-automaticos')->cron('*/1 * * * *'); //Cada 1 minuto
        // Ejecutar en local php artisan schedule:work para que se ejecute. En produccion configurar un cron * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1

    })
    ->create();
