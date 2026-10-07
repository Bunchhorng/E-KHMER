<?php

use Illuminate\Auth\AuthenticationException;
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
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'shop.manager' => \App\Http\Middleware\EnsureManagedShop::class,
        ]);

        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureStoreIsAvailable::class);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest('/');
        });

        // In production, never leak stack traces, file paths, SQL or env details
        // to API clients — even if APP_DEBUG was misconfigured. Standard
        // validation/auth/http errors already render safe, purpose-built payloads.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! app()->isProduction()) {
                return null;
            }

            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            return response()->json([
                'message' => $status >= 500 ? 'Server Error.' : $e->getMessage(),
            ], $status);
        });
    })->create();
