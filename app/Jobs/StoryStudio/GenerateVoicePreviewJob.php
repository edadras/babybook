<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryVoiceProfile;
use App\Services\StoryStudio\VoiceCastingService;

class GenerateVoicePreviewJob extends StoryStudioJob
{
    protected function queueName(): string
    {
        return 'audio';
    }

    protected function process(StoryProject $project): void
    {
        $profile = StoryVoiceProfile::find($this->targetId);
        if ($profile?->provider_voice_id) {
            app(VoiceCastingService::class)->preview($profile, $this->nonce);
        }
    }

    protected function markFailed(StoryProject $project): void {}
}
