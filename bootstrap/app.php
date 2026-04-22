<?php

use App\Http\Middleware\CheckCompanyRole;
use App\Http\Middleware\EnsureCompanySelected;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PerformanceRequestLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            PerformanceRequestLogger::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'company' => EnsureCompanySelected::class,
            'role' => CheckCompanyRole::class,
            'subscribed' => EnsureSubscriptionActive::class,
            'plan_feature' => EnsurePlanFeature::class,
            // Spatie (global app roles) — distinct from `role` (company pivot: owner, accountant).
            'spatie_role' => RoleMiddleware::class,
            'spatie_permission' => PermissionMiddleware::class,
        ]);

        // Chargily webhook must not be CSRF-protected.
        $middleware->validateCsrfTokens(except: [
            'webhooks/chargily',
            'chargilypay/webhook',
        ]);
    })
    // ->withExceptions(function (Exceptions $exceptions): void {
    //     $exceptions->render(function (ModelNotFoundException $e, Request $request) {
    //         if ($request->expectsJson() || $request->ajax()) {
    //             return response()->json([
    //                 'message' => 'Ressource introuvable',
    //                 'status' => 404,
    //             ], 404);
    //         }

    //         return Inertia::render('Error', [
    //             'status' => 404,
    //             'message' => 'La ressource demandée est introuvable.',
    //         ])->toResponse($request)->setStatusCode(404);
    //     });

    //     $exceptions->render(function (AuthorizationException $e, Request $request) {
    //         $message = "Vous n'êtes pas autorisé à effectuer cette action.";

    //         if ($request->expectsJson() || $request->ajax()) {
    //             return response()->json([
    //                 'message' => $message,
    //                 'status' => 403,
    //             ], 403);
    //         }

    //         return Inertia::render('Error', [
    //             'status' => 403,
    //             'message' => $message,
    //         ])->toResponse($request)->setStatusCode(403);
    //     });

    //     $exceptions->render(function (\RuntimeException $e, Request $request) {
    //         if ($request->expectsJson() || $request->ajax()) {
    //             return response()->json([
    //                 'message' => $e->getMessage(),
    //                 'status' => 422,
    //             ], 422);
    //         }

    //         return Inertia::render('Error', [
    //             'status' => 422,
    //             'message' => $e->getMessage(),
    //         ])->toResponse($request)->setStatusCode(422);
    //     });

    //     $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
    //         $status = $response->getStatusCode();

    //         if (in_array($status, [403, 404, 422, 500], true) && ! $request->expectsJson()) {
    //             return Inertia::render('Error', [
    //                 'status' => $status,
    //             ])->toResponse($request)->setStatusCode($status);
    //         }

    //         return $response;
    //     });
    // })->create();

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();
            $message = trim((string) $e->getMessage());
            $hasBusinessMessage = $message !== '';
            $isInertiaRequest = (bool) $request->header('X-Inertia');

            // Business errors raised via abort(422, '...') should surface as
            // user-friendly notifications in Inertia/web flows.
            if ($hasBusinessMessage && in_array($status, [403, 409, 422], true)) {
                if ($isInertiaRequest) {
                    if ($request->hasSession()) {
                        return back()
                            ->withErrors(['general' => $message])
                            ->with('error', $message);
                    }
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => $message,
                    ], $status);
                }

                if ($request->hasSession()) {
                    return back()->with('error', $message);
                }
            }

            return null;
        });
    })->create();
