<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\StoryDirectorService;

class AnalyzeStoryJob extends StoryStudioJob
{
    public int $timeout = 600;

    protected function queueName(): string
    {
        return 'text';
    }

    protected function process(StoryProject $project): void
    {
        $project->setWorkState('analysis', AssetStatus::Generating);
        app(StoryDirectorService::class)->analyzeAndStore($project, $this->nonce);
        $project->setWorkState('analysis', AssetStatus::Ready);
    }

    protected function markFailed(StoryProject $project): void
    {
        $project->setWorkState('analysis', AssetStatus::Failed);
    }

    protected function markCancelled(StoryProject $project): void
    {
        $project->setWorkState('analysis', AssetStatus::Pending);
    }
}
