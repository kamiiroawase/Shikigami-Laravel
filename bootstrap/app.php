<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('webhook')
                ->prefix('webhook')
                ->group(base_path('routes/webhook.php'));
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('webhook', []);
        $middleware->group('api', []);
        $middleware->group('web', []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (MethodNotAllowedHttpException $e) {
            throw new NotFoundHttpException();
        });
        $exceptions->render(function (Throwable $e) {
            if ($e instanceof HttpException) {
                return response('', $e->getStatusCode(), $e->getHeaders());
            }

            return response('', 500);
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('telegram-bot')->everySecond();
    })
    ->create();
