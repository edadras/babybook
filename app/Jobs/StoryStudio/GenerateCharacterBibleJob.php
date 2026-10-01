<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\StoryDirectorService;

class GenerateCharacterBibleJob extends StoryStudioJob
{
    protected function queueName(): string
    {
        return 'text';
    }

    protected function process(StoryProject $project): void
    {
        $character = StoryCharacter::findOrFail($this->targetId);
        app(StoryDirectorService::class)->refineCharacter($character, $this->nonce);
        app(DependencyInvalidator::class)->characterDesignChanged($character->fresh());
    }

    protected function markFailed(StoryProject $project): void {}
}
