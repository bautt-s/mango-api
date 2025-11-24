<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/v1/register',
            'api/v1/login',
            'api/v1/email-verification-code',
            'api/v1/verify-email',
            'api/v1/forgot-password',
            'api/v1/reset-password',
        ]);

        // Middleware aliases
        $middleware->alias([
            'ensureFrontendRequestsAreStateful' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'feature.access' => \App\Http\Middleware\CheckFeatureAccess::class, // ← AGREGADO
        ]);

        // API middleware group
        $middleware->group('api', [
            'ensureFrontendRequestsAreStateful',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (Throwable $e, Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $message = $e->getMessage() ?: 'Ocurrió un error en el servidor.';

                $trace = collect($e->getTrace());

                // buscar el PRIMER frame de tu código dentro de /app/
                $appFrame = $trace->first(function ($frame) {
                    return isset($frame['file'])
                        && str_contains($frame['file'], DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
                });

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => $message,

                    // excepción original
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),

                    // primera pista dentro de app/
                    'origin' => $appFrame ? [
                        'file' => $appFrame['file'],
                        'line' => $appFrame['line'],
                        'function' => $appFrame['function'] ?? null,
                        'class' => $appFrame['class'] ?? null,
                    ] : null,

                    // trace acotado
                    'trace' => $trace->take(10)->map(fn($t) => [
                        'file' => $t['file'] ?? null,
                        'line' => $t['line'] ?? null,
                        'function' => $t['function'] ?? null,
                        'class' => $t['class'] ?? null,
                    ]),
                ], $status);
            }
        });

        $exceptions->renderable(function (Illuminate\Validation\ValidationException $e, Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();