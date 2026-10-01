<?php

namespace App\Services\StoryStudio\Schema;

/**
 * JSON Schemas for structured AI output. They are written in the subset that
 * OpenAI strict mode accepts (every property required, no extra properties,
 * nullable via ["type", "null"]) and are re-validated server-side.
 */
final class StorySchemas
{
    public const MOTIONS = ['none', 'zoom_in', 'zoom_out', 'pan_left', 'pan_right', 'ken_burns'];

    public const MUSIC_MOODS = ['happy', 'adventure', 'mystery', 'calm', 'bedtime', 'sad', 'magical'];

    public const SAFETY_CATEGORIES = ['violence', 'sexual_content', 'extreme_fear', 'self_harm', 'dangerous_imitation', 'hate', 'adult_themes', 'other'];

    private static function obj(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private static function str(): array
    {
        return ['type' => 'string'];
    }

    private static function nullableStr(): array
    {
        return ['type' => ['string', 'null']];
    }

    private static function list(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }

    private static function enum(array $values): array
    {
        return ['type' => 'string', 'enum' => $values];
    }

    /**
     * @return array<string, mixed>
     */
    public static function characterProperties(): array
    {
        return [
            'personality' => self::str(),
            'visual_description' => self::str(),
            'clothing' => self::str(),
            'hair' => self::str(),
            'face' => self::str(),
            'colors' => self::list(self::str()),
            'height_relation' => self::str(),
            'special_features' => self::str(),
            'voice_description' => self::str(),
        ];
    }

    public static function analysis(): array
    {
        return self::obj([
            'story' => self::obj([
                'title' => self::str(),
                'summary' => self::str(),
                'moral' => self::str(),
                'tone' => self::str(),
                'estimated_duration_seconds' => ['type' => 'integer'],
                'visual_style_bible' => self::str(),
                'color_palette' => self::list(self::str()),
                'music_theme' => self::str(),
            ]),
            'safety' => self::obj([
                'overall' => self::enum(['safe', 'caution', 'review_required']),
                'flags' => self::list(self::obj([
                    'category' => self::enum(self::SAFETY_CATEGORIES),
                    'severity' => self::enum(['low', 'medium', 'high']),
                    'excerpt' => self::str(),
                    'note' => self::str(),
                ])),
            ]),
            'characters' => self::list(self::obj(array_merge([
                'id' => self::str(),
                'name' => self::str(),
                'role' => self::enum(['protagonist', 'supporting', 'antagonist', 'minor']),
                'is_major' => ['type' => 'boolean'],
                'age_group' => self::str(),
                'gender' => self::nullableStr(),
            ], self::characterProperties()))),
            'locations' => self::list(self::obj([
                'id' => self::str(),
                'name' => self::str(),
                'description' => self::str(),
                'visual_description' => self::str(),
                'default_time_of_day' => self::str(),
            ])),
            'scenes' => self::list(self::obj([
                'scene_number' => ['type' => 'integer'],
                'title' => self::str(),
                'location_id' => self::nullableStr(),
                'time_of_day' => self::str(),
                'duration_estimate' => ['type' => 'number'],
                'narration' => self::str(),
                'narration_en' => self::nullableStr(),
                'characters_present' => self::list(self::obj([
                    'character_id' => self::str(),
                    'action' => self::str(),
                    'position' => self::str(),
                ])),
                'dialogues' => self::list(self::obj([
                    'character_id' => self::str(),
                    'text' => self::str(),
                    'text_en' => self::nullableStr(),
                    'emotion' => self::str(),
                    'energy' => self::enum(['low', 'medium', 'high']),
                    'pace' => self::enum(['slow', 'normal', 'slightly_fast', 'fast']),
                    'delivery' => self::str(),
                ])),
                'visual' => self::obj([
                    'description' => self::str(),
                    'shot_type' => self::str(),
                    'camera_angle' => self::str(),
                    'lighting' => self::str(),
                    'mood' => self::str(),
                    'composition' => self::str(),
                    'character_actions' => self::list(self::str()),
                    'image_prompt' => self::str(),
                    'recommended_motion' => self::enum(self::MOTIONS),
                ]),
                'audio' => self::obj([
                    'ambience' => self::nullableStr(),
                    'sound_effects' => self::list(self::obj([
                        'description' => self::str(),
                        'prompt' => self::str(),
                        'timing' => self::enum(['start', 'with_narration', 'middle', 'end']),
                    ])),
                    'music_mood' => self::enum(self::MUSIC_MOODS),
                ]),
                'continuity' => self::obj([
                    'props' => self::list(self::str()),
                    'outfit_changes' => self::list(self::obj([
                        'character_id' => self::str(),
                        'change' => self::str(),
                    ])),
                    'weather' => self::str(),
                    'notes' => self::str(),
                ]),
            ])),
        ]);
    }

    public static function characterBible(): array
    {
        return self::obj(self::characterProperties());
    }
}
