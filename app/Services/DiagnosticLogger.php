<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiagnosticLogger
{
    public static function context(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? request() : null;
        if (! $request || ! $request->attributes->has('request_id')) {
            return ['source' => 'console'];
        }
        try {
            $userId = $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            $userId = null;
        }

        $entities = [];
        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if ($value instanceof Model && is_numeric($value->getKey())) {
                $entities[$key] = $value->getKey();
            }
        }

        return ['entities' => $entities, 'request_id' => $request->attributes->get('request_id'), 'method' => $request->method(), 'route' => $request->route()?->getName(), 'route_pattern' => $request->route()?->uri(), 'user_id' => $userId];
    }

    public static function exception(Throwable $exception): void
    {
        $trace = array_map(fn ($frame) => array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function'])), array_slice($exception->getTrace(), 0, 30));
        Log::channel('diagnostics')->error('application.exception', self::context() + [
            'exception_class' => $exception::class, 'code' => (string) $exception->getCode(),
            'file' => $exception->getFile(), 'line' => $exception->getLine(), 'trace' => $trace,
        ]);
    }
}
