<?php

namespace App\Services\StoryStudio\Exceptions;

/**
 * Local rate limit reached; the job should be released and retried later.
 */
class ProviderRateLimited extends ProviderException
{
    public function __construct(string $provider, public readonly int $retryAfterSeconds)
    {
        parent::__construct("Rate limit for {$provider} reached.", $provider, 429, true, 'rate_limited');
    }
}
