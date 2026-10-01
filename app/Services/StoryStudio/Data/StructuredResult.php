<?php

namespace App\Services\StoryStudio\Data;

final readonly class StructuredResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $requestId = null,
    ) {}
}
