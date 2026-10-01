<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\SoundKind;
use App\Enums\StoryStudio\Transition;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;

/**
 * Computes the story-oriented timeline: when narration, dialogue, effects,
 * ambience and music play inside each scene and where scenes sit globally.
 * Used by the mixer, the renderer, subtitles and the timeline editor.
 */
class TimelineService
{
    public const NARRATION_START_MS = 400;

    public const LINE_GAP_MS = 300;

    public const TAIL_MS = 900;

    public const MIN_SCENE_MS = 3000;

    /**
     * @return array{
     *     duration_ms: int,
     *     voice: list<array{kind: string, id: string, label: string, character_id: ?string, start_ms: int, duration_ms: int, volume: float, text: string, text_en: ?string, audio_id: ?string, estimated: bool}>,
     *     effects: list<array{kind: string, id: string, label: string, start_ms: int, duration_ms: int, volume: float, enabled: bool, loop: bool, audio_id: ?string}>,
     *     music: ?array{audio_id: ?string, volume: float, fade_in_ms: int, fade_out_ms: int, mood: ?string}
     * }
     */
    public function planScene(StoryScene $scene): array
    {
        $scene->loadMissing(['dialogues.character', 'soundEffects', 'project.settings']);
        $voice = [];
        $cursor = self::NARRATION_START_MS;

        if (trim((string) $scene->narration_original) !== '') {
            $audio = $scene->selectedAudio(AudioAssetType::Narration);
            $duration = $audio?->duration_ms ?: $this->estimateSpeechMs((string) $scene->narration_original);
            $voice[] = [
                'kind' => 'narration', 'id' => $scene->getKey(), 'label' => 'راوی', 'character_id' => null,
                'start_ms' => $cursor, 'duration_ms' => $duration, 'volume' => 1.0,
                'text' => (string) $scene->narration_original, 'text_en' => $scene->narration_en,
                'audio_id' => $audio?->getKey(), 'estimated' => ! $audio?->duration_ms,
            ];
            $cursor += $duration + self::LINE_GAP_MS;
        }

        foreach ($scene->dialogues as $dialogue) {
            $audio = $dialogue->selectedAudio();
            $duration = $audio?->duration_ms ?: $this->estimateSpeechMs($dialogue->text_original);
            $start = $dialogue->start_offset_ms ?? $cursor;
            $voice[] = [
                'kind' => 'dialogue', 'id' => $dialogue->getKey(), 'label' => $dialogue->character?->name ?? 'شخصیت',
                'character_id' => $dialogue->character_id, 'start_ms' => $start, 'duration_ms' => $duration,
                'volume' => (float) $dialogue->volume, 'text' => $dialogue->text_original, 'text_en' => $dialogue->text_en,
                'audio_id' => $audio?->getKey(), 'estimated' => ! $audio?->duration_ms,
            ];
            $cursor = max($cursor, $start + $duration + self::LINE_GAP_MS);
        }

        $voiceEnd = collect($voice)->max(fn ($item) => $item['start_ms'] + $item['duration_ms']) ?? 0;
        $natural = $voice === [] ? ($scene->duration_estimate_ms ?? 6000) : $voiceEnd + self::TAIL_MS;
        $duration = max(self::MIN_SCENE_MS, $natural, (int) $scene->duration_ms);

        $effects = [];
        foreach ($scene->soundEffects as $effect) {
            $audio = $effect->selectedAudio();
            $isAmbience = $effect->kind === SoundKind::Ambience;
            $effects[] = [
                'kind' => $effect->kind->value, 'id' => $effect->getKey(), 'label' => $effect->description,
                'start_ms' => $isAmbience ? 0 : min($effect->start_offset_ms, max(0, $duration - 500)),
                'duration_ms' => $isAmbience ? $duration : min($audio?->duration_ms ?: ($effect->duration_ms ?: 2000), $duration),
                'volume' => (float) $effect->volume, 'enabled' => $effect->enabled, 'loop' => $isAmbience || $effect->loop,
                'audio_id' => $audio?->getKey(),
            ];
        }

        $settings = $scene->project->settings;
        $musicId = $scene->music_audio_id ?: $settings?->default_music_audio_id;

        return [
            'duration_ms' => $duration,
            'voice' => $voice,
            'effects' => $effects,
            'music' => $musicId ? [
                'audio_id' => $musicId,
                'volume' => (float) ($scene->music_volume ?? $settings?->music_volume ?? 0.35),
                'fade_in_ms' => (int) $scene->music_fade_in_ms,
                'fade_out_ms' => (int) $scene->music_fade_out_ms,
                'mood' => $scene->music_mood,
            ] : null,
        ];
    }

    public function estimateSpeechMs(string $text): int
    {
        // About 12 Persian characters per second at a calm children's pace.
        return max(800, (int) round(mb_strlen(trim($text)) / 12 * 1000));
    }

    public function transitionMs(StoryScene $scene): int
    {
        if ($scene->transition === Transition::None) {
            return 40;
        }

        return (int) ($scene->transition_duration_ms ?? $scene->project->settings?->transition_duration_ms ?? 600);
    }

    /**
     * Global layout: scene start offsets accounting for transition overlaps.
     *
     * @param  list<int>  $durations
     * @param  list<int>  $transitions  transition length between scene i and i+1
     * @return array{starts: list<int>, total_ms: int, transitions: list<int>}
     */
    public function layout(array $durations, array $transitions): array
    {
        $starts = [];
        $length = 0;
        $clamped = [];

        foreach ($durations as $index => $duration) {
            if ($index === 0) {
                $starts[] = 0;
                $length = $duration;

                continue;
            }
            $overlap = min($transitions[$index - 1] ?? 0, (int) (0.4 * min($durations[$index - 1], $duration)));
            $clamped[] = $overlap;
            $starts[] = $length - $overlap;
            $length = $length + $duration - $overlap;
        }

        return ['starts' => $starts, 'total_ms' => $length, 'transitions' => $clamped];
    }

    /**
     * Data for the timeline editor.
     *
     * @return array{total_ms: int, scenes: list<array<string, mixed>>}
     */
    public function build(StoryProject $project): array
    {
        $scenes = $project->scenes()->with(['dialogues.character', 'soundEffects', 'assets', 'project.settings'])->get();
        $plans = [];
        $durations = [];
        $transitions = [];

        foreach ($scenes as $scene) {
            $plan = $this->planScene($scene);
            $mix = $scene->selectedAudio(AudioAssetType::SceneMix);
            $plan['duration_ms'] = $mix?->duration_ms && $mix->duration_ms > 0 ? $mix->duration_ms : $plan['duration_ms'];
            $plans[] = $plan;
            $durations[] = $plan['duration_ms'];
            $transitions[] = $this->transitionMs($scene);
        }

        $layout = $this->layout($durations, $transitions);

        $rows = [];
        foreach ($scenes->values() as $index => $scene) {
            $image = $scene->assets->where('type', AssetType::SceneImage)->firstWhere('selected', true);
            $rows[] = [
                'id' => $scene->getKey(),
                'number' => $scene->scene_number,
                'title' => $scene->title,
                'start_ms' => $layout['starts'][$index],
                'duration_ms' => $plans[$index]['duration_ms'],
                'duration_override_ms' => $scene->duration_ms,
                'transition' => $scene->transition->value,
                'transition_ms' => $transitions[$index],
                'motion' => $scene->motion->value,
                'image_id' => $image?->getKey(),
                'plan' => $plans[$index],
            ];
        }

        return ['total_ms' => $layout['total_ms'], 'scenes' => $rows];
    }
}
