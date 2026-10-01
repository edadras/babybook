<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\AudioGenerationService;

class GenerateSceneNarrationJob extends SceneJob
{
    protected function queueName(): string
    {
        return 'audio';
    }

    protected function stateKey(): string
    {
        return 'narration';
    }

    protected function work(StoryScene $scene): void
    {
        app(AudioGenerationService::class)->narration($scene, $this->nonce);
    }
}
