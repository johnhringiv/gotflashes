<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Add request ID to the request for tracing
        $request->headers->set('X-Request-ID', $requestId);

        // Log the incoming request
        Log::channel('structured')->debug('Request received', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'headers' => $this->filterSensitiveHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'body_size' => strlen($request->getContent()),
        ]);

        // Process the request
        $response = $next($request);

        // Calculate response time
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        // Log the response
        Log::channel('structured')->debug('Request completed', [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'response_size' => strlen($response->getContent()),
        ]);

        // Log performance metrics if response is slow
        $slowRequestThreshold = config('logging.slow_request_threshold_ms', 300);
        if ($duration > $slowRequestThreshold) {
            Log::channel('performance')->warning('Slow request detected', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => round($duration, 2),
                'threshold_ms' => $slowRequestThreshold,
                'user_id' => auth()->id(),
            ]);
        }

        return $response;
    }

    /**
     * Filter out sensitive headers from logging
     */
    private function filterSensitiveHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'php-auth-pw', 'x-csrf-token'];

        foreach ($sensitive as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = ['***FILTERED***'];
            }
        }

        return $headers;
    }
}
