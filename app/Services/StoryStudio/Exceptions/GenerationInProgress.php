<?php

namespace App\Services\StoryStudio\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The same generation is already being executed by another worker.
 */
class GenerationInProgress extends RuntimeException
{
    public function __construct(public readonly string $key, ?Throwable $previous = null)
    {
        parent::__construct('Generation '.$key.' is already running.', 0, $previous);
    }
}
