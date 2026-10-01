<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\VideoRenderService;

class RenderSceneJob extends SceneJob
{
    public int $timeout = 1800;

    protected function queueName(): string
    {
        return 'render';
    }

    protected function stateKey(): string
    {
        return 'video';
    }

    protected function work(StoryScene $scene): void
    {
        app(VideoRenderService::class)->renderScene($scene);
        app(DependencyInvalidator::class)->finalChanged($scene->project);
    }
}
