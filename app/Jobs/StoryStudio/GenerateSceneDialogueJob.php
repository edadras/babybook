<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\AudioGenerationService;

class GenerateSceneDialogueJob extends StoryStudioJob
{
    protected function queueName(): string
    {
        return 'audio';
    }

    protected function process(StoryProject $project): void
    {
        $dialogue = StoryDialogue::find($this->targetId);
        if (! $dialogue) {
            return;
        }

        $dialogue->forceFill(['status' => AssetStatus::Generating])->save();
        app(AudioGenerationService::class)->dialogue($dialogue, $this->nonce);
        $dialogue->forceFill(['status' => AssetStatus::Ready])->save();
    }

    protected function markFailed(StoryProject $project): void
    {
        StoryDialogue::whereKey($this->targetId)->update(['status' => AssetStatus::Failed->value]);
    }

    protected function markCancelled(StoryProject $project): void
    {
        StoryDialogue::whereKey($this->targetId)->update(['status' => AssetStatus::Pending->value]);
    }
}
