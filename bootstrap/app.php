<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Uses the "api" limiter defined in AppServiceProvider.
        $middleware->throttleApi();

        // JavaScript writes sidebar_state, so Laravel must not expect an encrypted value.
        // It holds no sensitive data: just "expanded" or "collapsed".
        $middleware->encryptCookies(except: ['sidebar_state']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // The exam rubric expects 400 for invalid input, not Laravel's default 422.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 400);
            }
        });

        // Laravel's default 404 message leaks the fully qualified model class; name the record instead.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $missing = $e->getPrevious();

            if ($request->is('api/*') && $missing instanceof ModelNotFoundException) {
                return response()->json(
                    ['message' => class_basename($missing->getModel()).' not found.'],
                    404
                );
            }
        });
    })->create();
