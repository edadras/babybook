<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\AudioMixService;
use App\Services\StoryStudio\DependencyInvalidator;

class MixSceneAudioJob extends SceneJob
{
    protected function queueName(): string
    {
        return 'render';
    }

    protected function stateKey(): string
    {
        return 'mix';
    }

    protected function work(StoryScene $scene): void
    {
        app(AudioMixService::class)->mixScene($scene);
        app(DependencyInvalidator::class)->sceneVideoChanged($scene);
    }
}
