<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'is.admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Standardize every API error into the { success:false, message, errors? } envelope.
        $exceptions->render(function (\Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null; // web routes keep default rendering
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            $map = [
                AuthenticationException::class => [401, 'Belum terautentikasi. Silakan login.'],
                AuthorizationException::class => [403, 'Forbidden.'],
                ModelNotFoundException::class => [404, 'Resource tidak ditemukan.'],
                NotFoundHttpException::class => [404, 'Endpoint atau resource tidak ditemukan.'],
                MethodNotAllowedHttpException::class => [405, 'Method HTTP tidak diizinkan untuk endpoint ini.'],
            ];

            foreach ($map as $class => [$status, $message]) {
                if ($e instanceof $class) {
                    return response()->json(['success' => false, 'message' => $message], $status);
                }
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'HTTP error',
                ], $e->getStatusCode());
            }

            // Unhandled -> 500. Hide internals unless APP_DEBUG is on.
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.',
            ], 500);
        });
    })->create();
