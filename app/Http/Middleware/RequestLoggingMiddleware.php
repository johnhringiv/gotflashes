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

        // Parse Livewire component details if present
        $livewireContext = $this->extractLivewireContext($request);

        // Log the incoming request
        Log::channel('structured')->debug('Request received', array_filter([
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
            'livewire' => $livewireContext, // Only present for Livewire requests
        ]));

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

    /**
     * Extract Livewire component context from request
     */
    private function extractLivewireContext(Request $request): ?array
    {
        // Check if this is a Livewire request
        if (! $request->hasHeader('X-Livewire')) {
            return null;
        }

        try {
            $payload = json_decode($request->getContent(), true);

            if (! $payload) {
                return null;
            }

            $context = [
                'component' => $payload['components'][0]['snapshot']['data']['__livewireId'] ?? $payload['components'][0]['snapshot']['memo']['name'] ?? 'unknown',
                'component_name' => $payload['components'][0]['snapshot']['memo']['name'] ?? 'unknown',
            ];

            // Extract method calls if present
            if (isset($payload['components'][0]['calls'])) {
                $context['calls'] = array_map(function ($call) {
                    return [
                        'method' => $call['method'] ?? 'unknown',
                        'params' => isset($call['params']) ? count($call['params']) : 0,
                    ];
                }, $payload['components'][0]['calls']);
            }

            // Extract updated properties if present
            if (isset($payload['components'][0]['updates'])) {
                $context['updates'] = array_map(function ($update) {
                    return [
                        'type' => $update['type'] ?? 'unknown',
                        'payload' => isset($update['payload']['value']) ? 'value_updated' : 'unknown',
                    ];
                }, $payload['components'][0]['updates']);
            }

            return $context;
        } catch (\Exception $e) {
            return ['error' => 'Failed to parse Livewire payload'];
        }
    }
}
