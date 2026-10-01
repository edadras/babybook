<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\RenderScope;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\VideoRenderService;
use Throwable;

/**
 * Final MP4 + MP3 + SRT + VTT + cover + ZIP.
 */
class RenderFinalVideoJob extends StoryStudioJob
{
    public int $timeout = 3600;

    public int $tries = 2;

    protected function queueName(): string
    {
        return 'render';
    }

    protected function process(StoryProject $project): void
    {
        $project->setWorkState('final', AssetStatus::Generating);
        $render = $project->renderJobs()->create(['scope' => RenderScope::Final, 'status' => GenerationStatus::Running, 'input_snapshot' => ['settings' => $project->settings?->toArray()]]);
        $render->forceFill(['started_at' => now()])->save();

        try {
            $outputs = app(VideoRenderService::class)->renderFinal($project);
        } catch (Throwable $exception) {
            $render->forceFill(['status' => GenerationStatus::Failed, 'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'finished_at' => now()])->save();

            throw $exception;
        }

        $render->forceFill(['status' => GenerationStatus::Succeeded, 'progress' => 100, 'output_asset_ids' => $outputs, 'finished_at' => now()])->save();
        $project->setWorkState('final', AssetStatus::Ready);
    }

    protected function markFailed(StoryProject $project): void
    {
        $project->setWorkState('final', AssetStatus::Failed);
    }

    protected function markCancelled(StoryProject $project): void
    {
        $project->setWorkState('final', AssetStatus::Pending);
    }
}
