<?php

use App\Http\Middleware\HandleIdempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'idempotent' => HandleIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. A valid Bearer token is required.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->hasStatus() ? $e->status() : Response::HTTP_FORBIDDEN;
                $message = $e->getMessage();

                if ($status === Response::HTTP_NOT_FOUND && ($message === '' || $message === 'This action is unauthorized.' || $message === 'Not Found')) {
                    $message = 'The requested resource was not found.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message ?: 'This action is unauthorized.',
                ], $status);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                if (! $request->is('api/v1/*')) {
                    $message = 'The requested endpoint was not found. Please verify the API base URL is versioned at /api/v1/.';
                } else {
                    $message = $e->getMessage();
                    if ($message === '' || $message === 'Not Found' || str_starts_with($message, 'The route ')) {
                        $message = 'The requested resource was not found.';
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], Response::HTTP_NOT_FOUND);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $message = $e->getMessage();

                if ($status === Response::HTTP_NOT_FOUND && ($message === '' || $message === 'Not Found')) {
                    $message = 'The requested resource was not found.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status, $e->getHeaders());
            }
        });
    })->create();
