<?php

namespace App\Services\StoryStudio\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A provider call failed. Messages never contain credentials.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider = 'unknown',
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(string $provider, string $env): self
    {
        return new self("کلید {$env} برای {$provider} تنظیم نشده است.", $provider, null, false, 'not_configured');
    }
}
