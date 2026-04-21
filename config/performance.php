<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Request / SQL observability toggle
    |--------------------------------------------------------------------------
    |
    | Keep this enabled in local and staging while tuning performance.
    | Disable in production unless actively profiling.
    |
    */
    'enabled' => (bool) env('PERF_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Slow query threshold (ms)
    |--------------------------------------------------------------------------
    */
    'slow_query_ms' => (int) env('PERF_SLOW_QUERY_MS', 50),

    /*
    |--------------------------------------------------------------------------
    | Slow request threshold (ms)
    |--------------------------------------------------------------------------
    */
    'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 400),
];

