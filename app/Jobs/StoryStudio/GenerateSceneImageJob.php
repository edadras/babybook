<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\ImageGenerationService;

class GenerateSceneImageJob extends SceneJob
{
    public function __construct(string $projectId, ?string $targetId = null, string $nonce = 'auto', public bool $select = true)
    {
        parent::__construct($projectId, $targetId, $nonce);
    }

    protected function queueName(): string
    {
        return 'image';
    }

    protected function stateKey(): string
    {
        return 'image';
    }

    protected function work(StoryScene $scene): void
    {
        app(ImageGenerationService::class)->sceneImage($scene, $this->nonce, $this->select);
    }
}
