<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // alias middleware route
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // Temporary: bypass CSRF for login to diagnose 419 issues
        // Note: This is safe locally; remove once login is confirmed working.
        $middleware->validateCsrfTokens(except: [
            'login',
        ]);

        // contoh lain (opsional): menambahkan ke group 'web'
        // $middleware->appendToGroup('web', [ \App\Http\Middleware\RoleMiddleware::class ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
