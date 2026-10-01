<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StorySoundEffect;
use App\Services\StoryStudio\AudioGenerationService;

class GenerateSoundEffectJob extends StoryStudioJob
{
    protected function queueName(): string
    {
        return 'audio';
    }

    protected function process(StoryProject $project): void
    {
        $effect = StorySoundEffect::find($this->targetId);
        if (! $effect) {
            return;
        }

        $effect->forceFill(['status' => AssetStatus::Generating])->save();
        app(AudioGenerationService::class)->soundEffect($effect, $this->nonce);
        $effect->forceFill(['status' => AssetStatus::Ready])->save();
    }

    protected function markFailed(StoryProject $project): void
    {
        StorySoundEffect::whereKey($this->targetId)->update(['status' => AssetStatus::Failed->value]);
    }

    protected function markCancelled(StoryProject $project): void
    {
        StorySoundEffect::whereKey($this->targetId)->update(['status' => AssetStatus::Pending->value]);
    }
}
