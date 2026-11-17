<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\WebhookRejectedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['webhooks/pixelforge']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (PixelforgeException $exception, Request $request) {
            if (! $request->expectsJson()) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $exception->requestWasAccepted
                        ? 'Pixelforge received the request, but its status is not confirmed yet. Check your generations before retrying.'
                        : 'We could not submit this image request. Please try again.',
                ]);

                return back();
            }

            $status = $exception->requestWasAccepted
                ? HttpResponse::HTTP_ACCEPTED
                : ($exception->statusCode ?? HttpResponse::HTTP_BAD_GATEWAY);
            $response = response()->json(
                ['message' => $exception->getMessage()],
                $status,
            );
            if ($exception->retryAfter !== null) {
                $response->header('Retry-After', (string) $exception->retryAfter);
            }

            return $response;
        });

        $exceptions->render(fn (WebhookRejectedException $exception) => response()->json(
            ['message' => $exception->getMessage()],
            HttpResponse::HTTP_BAD_REQUEST,
        ));
    })->create();
