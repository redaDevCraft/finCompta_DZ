<?php

use App\Http\Middleware\EnsureCompanySelected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'company' => EnsureCompanySelected::class,
            'role' => \App\Http\Middleware\CheckCompanyRole::class,
        ]);

        //
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
        //
    })->create();
    