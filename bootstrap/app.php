<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Los visitantes sin sesion van al login de Fortify.
         * Sustituye el antiguo App\Http\Middleware\Authenticate.
         */
        $middleware->redirectGuestsTo('/login');

        /*
         * Los usuarios ya autenticados que entran a /login o /register
         * vuelven al panel. Sustituye el antiguo RedirectIfAuthenticated.
         */
        $middleware->redirectUsersTo('/');

        /*
         * SweetAlert necesita su middleware dentro del grupo 'web' para
         * poder leer y escribir los mensajes flash de la sesion.
         */
        $middleware->web(append: [
            \RealRashid\SweetAlert\Http\Middleware\ToSweetAlert::class,
        ]);

        /*
         * Alias de los middleware de spatie/laravel-permission.
         * Antes vivian en App\Http\Kernel::$routeMiddleware.
         */
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        //
    })
    ->withCommands()
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Estos campos nunca se devuelven al formulario cuando hay
         * un error de validacion. Proviene del antiguo
         * App\Exceptions\Handler::$dontFlash.
         */
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
