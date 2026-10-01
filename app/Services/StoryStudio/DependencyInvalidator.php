<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Models\StoryStudio\StorySoundEffect;

/**
 * Marks only the downstream dependents of a change as stale, e.g.:
 *   dialogue text → dialogue audio → scene mix → scene video → final render
 * Character images and other scenes are left untouched.
 */
class DependencyInvalidator
{
    private const READY_LIKE = [AssetStatus::Ready, AssetStatus::Approved, AssetStatus::Failed];

    public function dialogueChanged(StoryDialogue $dialogue): void
    {
        $this->markItem($dialogue);
        $this->sceneOutputsChanged($dialogue->scene);
    }

    public function narrationChanged(StoryScene $scene): void
    {
        $this->markScene($scene, ['narration', 'mix', 'video']);
        $this->finalChanged($scene->project);
    }

    public function visualChanged(StoryScene $scene): void
    {
        $this->markScene($scene, ['image', 'video']);
        $this->finalChanged($scene->project);
    }

    /**
     * A new image version was selected: the clip must be re-rendered, nothing else.
     */
    public function imageSelected(StoryScene $scene): void
    {
        $this->markScene($scene, ['video']);
        $this->finalChanged($scene->project);
    }

    public function soundEffectChanged(StorySoundEffect $effect, bool $audioToo = true): void
    {
        if ($audioToo) {
            $this->markItem($effect);
        }
        $this->sceneOutputsChanged($effect->scene);
    }

    /**
     * Mixing inputs changed (volume, offsets, music, timing): re-mix and re-render the scene.
     */
    public function sceneOutputsChanged(StoryScene $scene): void
    {
        $this->markScene($scene, ['mix', 'video']);
        $this->finalChanged($scene->project);
    }

    /**
     * Only the video needs re-rendering (motion, transition, image duration).
     */
    public function sceneVideoChanged(StoryScene $scene): void
    {
        $this->markScene($scene, ['video']);
        $this->finalChanged($scene->project);
    }

    public function characterVoiceChanged(StoryCharacter $character): void
    {
        if ($character->is_narrator) {
            foreach ($character->project->scenes as $scene) {
                if ($scene->narration_original) {
                    $this->narrationChanged($scene);
                }
            }

            return;
        }

        foreach ($character->dialogues()->with('scene')->get() as $dialogue) {
            if ($dialogue->scene) {
                $this->dialogueChanged($dialogue);
            }
        }
    }

    public function characterDesignChanged(StoryCharacter $character): void
    {
        $character->forceFill(['status' => AssetStatus::Stale])->save();

        foreach ($character->scenes as $scene) {
            $this->visualChanged($scene);
        }
    }

    /**
     * The scene list changed (reorder, delete, split): only the final render is affected.
     */
    public function finalChanged(StoryProject $project): void
    {
        $state = $project->fresh()->work_state ?? [];
        foreach (['final', 'preview'] as $key) {
            if (isset($state[$key]) && $state[$key] !== AssetStatus::Pending->value) {
                $state[$key] = AssetStatus::Stale->value;
            }
        }
        $project->forceFill(['work_state' => $state, 'review_approved_at' => null])->save();
    }

    /**
     * @param  list<string>  $keys
     */
    private function markScene(StoryScene $scene, array $keys): void
    {
        $states = [];
        foreach ($keys as $key) {
            $current = $scene->fresh()?->workState($key) ?? AssetStatus::Pending;
            // Pending items will be generated anyway; only completed (or failed) outputs become stale.
            if (in_array($current, [...self::READY_LIKE, AssetStatus::Queued, AssetStatus::Generating], true)) {
                $states[$key] = AssetStatus::Stale;
            }
        }

        if ($states !== []) {
            $scene->setWorkState($states);
        }
    }

    private function markItem(StoryDialogue|StorySoundEffect $item): void
    {
        if ($item->status !== AssetStatus::Pending) {
            $item->forceFill(['status' => AssetStatus::Stale])->save();
        }
    }
}
