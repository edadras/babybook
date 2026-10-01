<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryScene;
use Illuminate\Support\Collection;

/**
 * Builds the immutable character descriptor injected into every image prompt
 * that contains the character, from the Character Bible columns only, so a
 * prompt can never redefine a character.
 */
class CharacterReferenceService
{
    public const REFERENCE_VIEWS = [
        AssetType::CharacterFront,
        AssetType::CharacterThreeQuarter,
        AssetType::CharacterFullBody,
    ];

    public function descriptor(StoryCharacter $character): string
    {
        $colors = $character->colors ? 'signature colors: '.implode(', ', $character->colors) : null;

        $parts = array_filter([
            $character->visual_description,
            $character->clothing ? 'wearing '.$character->clothing : null,
            $character->hair ? 'hair/fur: '.$character->hair : null,
            $character->face ? 'face: '.$character->face : null,
            $colors,
            $character->height_relation ? 'size: '.$character->height_relation : null,
            $character->special_features ? 'always has: '.$character->special_features : null,
        ]);

        return sprintf('%s (id: %s): %s.', $character->name, $character->key, rtrim(implode('; ', $parts), '. '));
    }

    /**
     * @return Collection<int, StoryCharacter>
     */
    public function charactersIn(StoryScene $scene): Collection
    {
        return $scene->characters()->where('is_narrator', false)->orderBy('sort_order')->get();
    }

    public function blockForScene(StoryScene $scene): string
    {
        $characters = $this->charactersIn($scene);

        if ($characters->isEmpty()) {
            return '';
        }

        return "CHARACTERS (locked designs — draw exactly as described, identical in every scene):\n- "
            .$characters->map(fn (StoryCharacter $c) => $this->descriptor($c))->implode("\n- ");
    }

    /**
     * Absolute local paths of approved reference sheets for characters in the scene.
     *
     * @return list<string>
     */
    public function referenceImagePaths(StoryScene $scene, AssetStorageService $storage): array
    {
        $paths = [];

        foreach ($this->charactersIn($scene) as $character) {
            $reference = $character->selectedReference(AssetType::CharacterFront)
                ?? $character->selectedReference(AssetType::CharacterFullBody);

            if ($reference?->file_path && ($character->character_locked || $character->reference_asset_id)) {
                $paths[] = $storage->localPath($reference);
            }
        }

        return $paths;
    }
}
