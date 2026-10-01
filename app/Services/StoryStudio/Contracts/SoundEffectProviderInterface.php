<?php

namespace App\Services\StoryStudio\Contracts;

use App\Services\StoryStudio\Data\AudioResult;

interface SoundEffectProviderInterface
{
    public function generate(string $prompt, ?float $durationSeconds = null): AudioResult;

    public function name(): string;

    public function model(): string;
}
