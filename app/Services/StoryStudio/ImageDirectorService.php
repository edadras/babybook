<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;

/**
 * Composes production-ready image prompts:
 * GLOBAL_STYLE + CHARACTER_REFERENCES + LOCATION + SCENE + COMPOSITION + CAMERA + LIGHTING + CONTINUITY.
 */
class ImageDirectorService
{
    public function __construct(
        private readonly CharacterReferenceService $characters,
        private readonly VisualContinuityService $continuity,
    ) {}

    public function styleBlock(StoryProject $project): string
    {
        $bible = $project->style_bible ?? [];

        return 'GLOBAL STYLE: '.implode(' ', array_filter([
            $bible['base'] ?? $project->visual_style->promptFragment(),
            $bible['global'] ?? null,
            ! empty($bible['palette']) ? 'Palette: '.implode(', ', $bible['palette']).'.' : null,
            $project->custom_visual_instructions ? 'Art direction: '.$project->custom_visual_instructions : null,
            'Audience: children aged '.$project->target_age->value.'; gentle, friendly, never frightening.',
        ]));
    }

    public function composeScenePrompt(StoryScene $scene): string
    {
        $scene->loadMissing('project', 'location');
        $visual = $scene->visual ?? [];
        $project = $scene->project;
        $orientation = match ($project->aspect_ratio->value) {
            '9:16' => 'vertical 9:16 frame, keep key action in the central safe area',
            '1:1' => 'square 1:1 frame',
            default => 'wide 16:9 cinematic frame',
        };

        $sections = [
            $this->styleBlock($project),
            $this->characters->blockForScene($scene),
            $scene->location
                ? 'LOCATION: '.$scene->location->name.' — '.$scene->location->visual_description
                : null,
            'SCENE: '.($scene->director_prompt ?: ($visual['description'] ?? $scene->narration_original)),
            ! empty($visual['character_actions']) ? 'ACTIONS: '.implode('; ', $visual['character_actions']) : null,
            'COMPOSITION: '.trim(($visual['composition'] ?? '').'; '.$orientation, '; '),
            'CAMERA: '.trim(($visual['shot_type'] ?? 'medium shot').', '.($visual['camera_angle'] ?? 'eye level'), ', '),
            'LIGHTING & MOOD: '.trim(($visual['lighting'] ?? 'soft light').'; time of day: '.($scene->time_of_day ?: 'day').'; mood: '.($visual['mood'] ?? 'warm'), '; '),
            $this->continuity->block($scene),
            'RULES: one single illustration, no text, no letters, no captions, no watermarks, no frames or panels.',
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Refresh the composed prompt unless the administrator edited it by hand.
     */
    public function prepareScene(StoryScene $scene): void
    {
        if ($scene->prompt_edited && $scene->final_image_prompt) {
            return;
        }

        $scene->forceFill(['final_image_prompt' => $this->composeScenePrompt($scene)])->save();
    }

    public function characterReferencePrompt(StoryCharacter $character, AssetType $view): string
    {
        if ($character->reference_prompt) {
            return $character->reference_prompt."\n\nVIEW: ".$this->viewText($view);
        }

        return implode("\n\n", [
            $this->styleBlock($character->project),
            'CHARACTER REFERENCE SHEET — '.$this->characters->descriptor($character),
            'Personality to convey in pose and expression: '.$character->personality,
            'VIEW: '.$this->viewText($view),
            'Plain soft neutral background, even lighting, the full character clearly visible, no other characters, no text.',
        ]);
    }

    public function coverPrompt(StoryProject $project): string
    {
        $main = $project->characters()->where('is_narrator', false)->where('is_major', true)->orderBy('sort_order')->limit(3)->get();

        return implode("\n\n", array_filter([
            $this->styleBlock($project),
            $main->isNotEmpty() ? "CHARACTERS (locked designs):\n- ".$main->map(fn ($c) => $this->characters->descriptor($c))->implode("\n- ") : null,
            'BOOK COVER ARTWORK for the story: '.data_get($project->story_metadata, 'summary', $project->title),
            'Iconic, inviting composition with the main characters together; leave calm empty space in the upper third for a title that will be added later.',
            'Absolutely no text, letters, title or typography in the image.',
        ]));
    }

    private function viewText(AssetType $view): string
    {
        return match ($view) {
            AssetType::CharacterThreeQuarter => 'three-quarter view, standing, friendly expression',
            AssetType::CharacterFullBody => 'full body, front view, standing in a neutral pose, entire outfit visible',
            default => 'front view portrait from the waist up, looking at the viewer',
        };
    }
}
