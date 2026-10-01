<?php

namespace App\Services\StoryStudio;

use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;

/**
 * Translates the director's voice direction (emotion, energy, pace, delivery)
 * into provider-compatible expressive tags (ElevenLabs v3 audio tags).
 * The spoken words themselves stay exactly as written.
 */
class VoiceDirectorService
{
    private const EMOTIONS = [
        'happy' => 'happy', 'joyful' => 'happy', 'excited' => 'excited', 'worried' => 'worried', 'anxious' => 'nervous',
        'scared' => 'nervous', 'afraid' => 'nervous', 'sad' => 'sad', 'curious' => 'curious', 'surprised' => 'surprised',
        'angry' => 'annoyed', 'calm' => 'calm', 'proud' => 'proud', 'tired' => 'tired', 'shy' => 'shy', 'playful' => 'playful',
        'gentle' => 'gentle', 'loving' => 'warmly', 'kind' => 'warmly',
    ];

    /**
     * @return list<string>
     */
    public function tagsForDialogue(StoryDialogue $dialogue): array
    {
        $tags = [];
        $emotion = strtolower(trim((string) $dialogue->emotion));

        foreach (self::EMOTIONS as $needle => $tag) {
            if ($emotion !== '' && str_contains($emotion, $needle)) {
                $tags[] = $tag;
                break;
            }
        }

        $delivery = strtolower((string) $dialogue->delivery);
        $tags[] = match (true) {
            str_contains($delivery, 'whisper') => 'whispers',
            str_contains($delivery, 'shout'), str_contains($delivery, 'calling'), str_contains($delivery, 'distance') => 'shouting',
            str_contains($delivery, 'laugh') => 'laughs',
            str_contains($delivery, 'sigh') => 'sighs',
            str_contains($delivery, 'giggl') => 'giggles',
            default => null,
        };

        if ($dialogue->pace === 'slow') {
            $tags[] = 'slowly';
        } elseif (in_array($dialogue->pace, ['fast', 'slightly_fast'], true)) {
            $tags[] = 'quickly';
        }

        return array_values(array_unique(array_filter($tags)));
    }

    /**
     * @return list<string>
     */
    public function tagsForNarration(StoryProject $project): array
    {
        return match ($project->narration_style->value) {
            'bedtime' => ['softly', 'calm'],
            'playful' => ['cheerfully'],
            'dramatic' => ['storytelling'],
            'educational' => ['clearly'],
            default => ['warmly'],
        };
    }

    /**
     * @param  list<string>  $tags
     */
    public function applyTags(string $text, array $tags, bool $supported): string
    {
        if (! $supported || $tags === []) {
            return $text;
        }

        return implode(' ', array_map(fn ($tag) => '['.$tag.']', $tags)).' '.$text;
    }

    /**
     * Voice settings tuned per energy level (stability lower = more expressive).
     *
     * @return array<string, float|bool>
     */
    public function voiceSettings(?string $energy): array
    {
        return match ($energy) {
            'high' => ['stability' => 0.3, 'similarity_boost' => 0.8, 'style' => 0.5, 'use_speaker_boost' => true],
            'low' => ['stability' => 0.6, 'similarity_boost' => 0.8, 'style' => 0.2, 'use_speaker_boost' => true],
            default => ['stability' => 0.5, 'similarity_boost' => 0.8, 'style' => 0.35, 'use_speaker_boost' => true],
        };
    }
}
