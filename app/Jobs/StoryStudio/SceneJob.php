<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;

/**
 * A job producing one output of a scene, tracked in scene.work_state[$key].
 */
abstract class SceneJob extends StoryStudioJob
{
    abstract protected function stateKey(): string;

    abstract protected function work(StoryScene $scene): void;

    protected function process(StoryProject $project): void
    {
        $scene = StoryScene::find($this->targetId);
        if (! $scene) {
            return;
        }

        $scene->setWorkState([$this->stateKey() => AssetStatus::Generating]);
        $this->work($scene);
        $scene->refresh()->setWorkState([$this->stateKey() => AssetStatus::Ready]);
    }

    protected function markFailed(StoryProject $project): void
    {
        StoryScene::find($this->targetId)?->setWorkState([$this->stateKey() => AssetStatus::Failed]);
    }

    protected function markCancelled(StoryProject $project): void
    {
        StoryScene::find($this->targetId)?->setWorkState([$this->stateKey() => AssetStatus::Pending]);
    }
}
