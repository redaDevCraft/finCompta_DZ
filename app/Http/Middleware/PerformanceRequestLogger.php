<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceRequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.enabled', false)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $start = microtime(true);
        $queryCount = 0;
        $totalSqlMs = 0.0;
        $maxSqlMs = 0.0;
        $slowQueryMs = (int) config('performance.slow_query_ms', 50);

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$totalSqlMs, &$maxSqlMs, $slowQueryMs): void {
            $queryCount++;
            $totalSqlMs += $query->time;
            $maxSqlMs = max($maxSqlMs, $query->time);

            if ($query->time >= $slowQueryMs) {
                Log::channel('performance')->warning('sql.slow_query', [
                    'sql_ms' => round($query->time, 2),
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'connection' => $query->connectionName,
                ]);
            }
        });

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $payloadBytes = $this->resolvePayloadSize($response);

        $context = [
            'method' => $request->getMethod(),
            'path' => '/'.$request->path(),
            'route' => optional($request->route())->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'query_count' => $queryCount,
            'sql_time_ms' => round($totalSqlMs, 2),
            'max_query_ms' => round($maxSqlMs, 2),
            'payload_kb' => $payloadBytes !== null ? round($payloadBytes / 1024, 2) : null,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        $isSlowRequest = $durationMs >= (int) config('performance.slow_request_ms', 400);
        $loggerMethod = $isSlowRequest ? 'warning' : 'info';

        Log::channel('performance')->{$loggerMethod}('http.request_performance', $context);

        return $response;
    }

    private function resolvePayloadSize(Response $response): ?int
    {
        if (method_exists($response, 'headers')) {
            $contentLength = $response->headers->get('Content-Length');
            if (is_numeric($contentLength)) {
                return (int) $contentLength;
            }
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content)) {
                return strlen($content);
            }
        }

        return null;
    }
}

