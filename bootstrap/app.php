<?php

use App\Http\Middleware\RequestDiagnostics;
use App\Http\Middleware\RequireSellerCash;
use App\Services\DiagnosticLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestDiagnostics::class);
        $middleware->appendToGroup('web', RequireSellerCash::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception) {
            DiagnosticLogger::exception($exception);

            return false;
        });
        $exceptions->respond(function ($response, $exception, $request) {
            if ($exception instanceof ValidationException) {
                $request->attributes->set('diagnostic_validation_fields', array_values(array_filter(array_keys($exception->errors()), fn ($key) => preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $key))));
                if ($request->hasSession() && $response->isRedirection()) {
                    $request->session()->flash('diagnostic_error_id', $request->attributes->get('request_id'));
                }
            }
            if ($response->getStatusCode() < 500) {
                return $response;
            }
            $id = $request->attributes->get('request_id');
            $message = 'No fue posible completar la operación. Referencia: '.$id;
            $headers = $response->headers->has('Retry-After') ? ['Retry-After' => $response->headers->get('Retry-After')] : [];

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'request_id' => $id], $response->getStatusCode(), $headers)
                : response()->view('errors.server', ['reference' => $id], $response->getStatusCode(), $headers);
        });
    })->create();
