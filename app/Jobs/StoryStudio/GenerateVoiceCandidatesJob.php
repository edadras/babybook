<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\VoiceCastingService;

/**
 * Designs candidate voices (manual mode) or casts automatically (auto mode).
 */
class GenerateVoiceCandidatesJob extends StoryStudioJob
{
    public function __construct(string $projectId, ?string $targetId = null, string $nonce = 'auto', public bool $autoSelect = false)
    {
        parent::__construct($projectId, $targetId, $nonce);
    }

    protected function queueName(): string
    {
        return 'audio';
    }

    protected function process(StoryProject $project): void
    {
        $character = StoryCharacter::findOrFail($this->targetId);
        $character->forceFill(['voice_status' => AssetStatus::Generating])->save();
        $casting = app(VoiceCastingService::class);

        $this->autoSelect
            ? $casting->autoCast($character, $this->nonce)
            : $casting->designCandidates($character, $this->nonce);

        $character->refresh();
        $character->forceFill(['voice_status' => $character->voice_profile_id ? AssetStatus::Approved : AssetStatus::Ready])->save();
    }

    protected function markFailed(StoryProject $project): void
    {
        StoryCharacter::whereKey($this->targetId)->update(['voice_status' => AssetStatus::Failed->value]);
    }

    protected function markCancelled(StoryProject $project): void
    {
        StoryCharacter::whereKey($this->targetId)->update(['voice_status' => AssetStatus::Pending->value]);
    }
}
