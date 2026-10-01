<?php

namespace App\Services\StoryStudio\Exceptions;

use RuntimeException;

/**
 * Structured AI output failed schema validation; nothing was stored.
 */
class InvalidAiOutputException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('خروجی هوش مصنوعی با ساختار مورد انتظار مطابقت ندارد: '.implode(' | ', array_slice($errors, 0, 5)));
    }
}
