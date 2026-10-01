<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\ImageGenerationService;

class GenerateCoverArtJob extends StoryStudioJob
{
    protected function queueName(): string
    {
        return 'image';
    }

    protected function process(StoryProject $project): void
    {
        $project->setWorkState('cover_art', AssetStatus::Generating);
        app(ImageGenerationService::class)->coverArt($project, $this->nonce);
        $project->setWorkState('cover_art', AssetStatus::Ready);
    }

    protected function markFailed(StoryProject $project): void
    {
        $project->setWorkState('cover_art', AssetStatus::Failed);
    }

    protected function markCancelled(StoryProject $project): void
    {
        $project->setWorkState('cover_art', AssetStatus::Pending);
    }
}
