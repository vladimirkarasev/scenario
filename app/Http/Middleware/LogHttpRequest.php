<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class LogHttpRequest
{
    private float $startTime = 0.0;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->startTime = microtime(true);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $duration = (int)((microtime(true) - $this->startTime) * 1000);

        $this->logger->info('HTTP request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);
    }

    private function shouldSkip(Request $request): bool
    {
        $path = $request->path();

        return $path === 'up'
            || str_starts_with($path, '_')
            || preg_match('/\.(js|css|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|map)$/', $path) === 1;
    }
}
