<?php

namespace App\Services\StoryStudio;

final readonly class GenerationOutcome
{
    /**
     * @param  array<string, mixed>  $summary  redacted response summary (e.g. created asset ids)
     */
    public function __construct(
        public mixed $result,
        public int $inputUnits = 0,
        public int $outputUnits = 0,
        public string $unitType = 'calls',
        public ?string $requestId = null,
        public array $summary = [],
        public ?float $actualCost = null,
    ) {}
}
