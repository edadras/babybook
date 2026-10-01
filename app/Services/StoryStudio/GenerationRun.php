<?php

namespace App\Services\StoryStudio;

use App\Models\StoryStudio\StoryGenerationJob;

final readonly class GenerationRun
{
    public function __construct(
        public StoryGenerationJob $ledger,
        public mixed $result,
        public bool $reused,
    ) {}
}
