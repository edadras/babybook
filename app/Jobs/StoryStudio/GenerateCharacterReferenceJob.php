<?php

namespace App\Jobs\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\CharacterReferenceService;
use App\Services\StoryStudio\ImageGenerationService;

/**
 * Front, 3/4 and full-body reference sheets for one character.
 */
class GenerateCharacterReferenceJob extends StoryStudioJob
{
    public function __construct(string $projectId, ?string $targetId = null, string $nonce = 'auto', public ?string $view = null)
    {
        parent::__construct($projectId, $targetId, $nonce);
    }

    protected function queueName(): string
    {
        return 'image';
    }

    protected function process(StoryProject $project): void
    {
        $character = StoryCharacter::findOrFail($this->targetId);
        $character->forceFill(['status' => AssetStatus::Generating])->save();
        $views = $this->view ? [AssetType::from($this->view)] : CharacterReferenceService::REFERENCE_VIEWS;

        foreach ($views as $view) {
            app(ImageGenerationService::class)->characterReference($character->fresh(), $view, $this->nonce);
        }

        $character->forceFill(['status' => AssetStatus::Ready])->save();
    }

    protected function markFailed(StoryProject $project): void
    {
        StoryCharacter::whereKey($this->targetId)->update(['status' => AssetStatus::Failed->value]);
    }

    protected function markCancelled(StoryProject $project): void
    {
        StoryCharacter::whereKey($this->targetId)->update(['status' => AssetStatus::Pending->value]);
    }
}
