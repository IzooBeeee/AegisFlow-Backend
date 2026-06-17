<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trả về 401 Unauthorized thay vì redirect về route 'login' chưa định nghĩa đối với API
        $middleware->redirectGuestsTo(fn () => null);

        // Đăng ký Spatie Permission middleware aliases
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // CORS cho API — cho phép frontend truy cập
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Throttle cho tất cả API routes
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom error rendering cho ValidationException
        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        });
    })
    ->withProviders([
        AppServiceProvider::class,
    ])
    ->create();
