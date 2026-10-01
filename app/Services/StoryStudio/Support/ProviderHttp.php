<?php

namespace App\Services\StoryStudio\Support;

use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Exceptions\ProviderRateLimited;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared HTTP plumbing for AI providers: timeouts, retries with backoff on
 * 429/5xx/connection errors, a per-provider rate limiter, and logging that
 * never includes credentials or binary payloads.
 */
class ProviderHttp
{
    public static function client(string $provider, string $baseUrl, array $headers, int $timeout): PendingRequest
    {
        $retries = max(0, (int) config('story-studio.http.retries', 3));
        $baseMs = (int) config('story-studio.http.retry_base_ms', 1000);

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(15)
            ->retry(
                $retries + 1,
                fn (int $attempt) => $baseMs * (2 ** ($attempt - 1)),
                function (Throwable $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && in_array($exception->response->status(), [408, 409, 429, 500, 502, 503, 504], true);
                },
                throw: false,
            );
    }

    public static function throttle(string $provider): void
    {
        $limit = (int) config("story-studio.http.rate_limits.{$provider}", 60);
        $key = 'story-studio:provider:'.$provider;

        if ($limit > 0 && ! RateLimiter::attempt($key, $limit, fn () => true, 60)) {
            throw new ProviderRateLimited($provider, max(1, RateLimiter::availableIn($key)));
        }
    }

    /**
     * Log the call and convert error responses to ProviderException.
     */
    public static function check(string $provider, string $operation, Response $response, float $startedAt): Response
    {
        $context = [
            'provider' => $provider,
            'operation' => $operation,
            'status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request_id' => $response->header('x-request-id') ?: $response->header('request-id') ?: null,
        ];

        if ($response->successful()) {
            Log::channel(config('story-studio.log_channel'))->info('story-studio.provider_call', $context);

            return $response;
        }

        $message = self::errorMessage($response);
        Log::channel(config('story-studio.log_channel'))->warning('story-studio.provider_error', $context + ['error' => $message]);

        throw new ProviderException(
            "{$provider} ({$response->status()}): {$message}",
            $provider,
            $response->status(),
            in_array($response->status(), [408, 409, 429, 500, 502, 503, 504], true),
            (string) (data_get($response->json(), 'error.code') ?? data_get($response->json(), 'detail.status') ?? ''),
        );
    }

    public static function redact(string $text): string
    {
        $text = preg_replace('/(sk-[A-Za-z0-9_\-]{6})[A-Za-z0-9_\-]+/', '$1…', $text) ?? $text;
        $text = preg_replace('/("?(?:api[_-]?key|xi-api-key|authorization)"?\s*[:=]\s*"?)[^"\s,}]+/i', '$1[redacted]', $text) ?? $text;

        return Str::limit($text, 500);
    }

    private static function errorMessage(Response $response): string
    {
        $json = $response->json();
        $message = data_get($json, 'error.message')
            ?? data_get($json, 'detail.message')
            ?? (is_string(data_get($json, 'detail')) ? data_get($json, 'detail') : null)
            ?? $response->body();

        return self::redact((string) $message);
    }
}
