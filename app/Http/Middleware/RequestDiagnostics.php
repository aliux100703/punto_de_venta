<?php

namespace App\Http\Middleware;

use App\Services\DiagnosticLogger;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RequestDiagnostics
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('request_id', (string) Str::uuid());
        $start = microtime(true);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            report($exception);
            $response = app(ExceptionHandler::class)->render($request, $exception);
        }
        $status = $response->getStatusCode();
        $duration = round((microtime(true) - $start) * 1000, 2);
        $id = $request->attributes->get('request_id');
        $response->headers->set('X-Request-ID', $id);
        if ($status >= 400 && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $data['request_id'] = $id;
                $response->setData($data);
            }
        }
        $rejected = $status === 422 || ($request->hasSession() && $request->session()->has('errors'));
        $slow = $duration >= (int) config('logging.diagnostics_slow_ms', 1500);
        if (! $request->isMethodSafe() || $status >= 400 || $slow) {
            $level = $status >= 500 ? 'error' : (($status >= 400 || $rejected || $slow) ? 'warning' : 'info');
            Log::channel('diagnostics')->log($level, 'http.request', DiagnosticLogger::context($request) + ['status' => $status, 'duration_ms' => $duration, 'validation_rejected' => $rejected, 'validation_fields' => $request->attributes->get('diagnostic_validation_fields', []), 'slow' => $slow]);
        }

        return $response;
    }
}
