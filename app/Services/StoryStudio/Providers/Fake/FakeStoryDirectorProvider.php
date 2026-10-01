<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Contracts\StoryDirectorProviderInterface;
use App\Services\StoryStudio\Data\StructuredResult;

/**
 * Deterministic offline director used in tests and demo mode.
 * It splits the story into paragraphs and extracts «quoted» lines as dialogue.
 */
class FakeStoryDirectorProvider implements StoryDirectorProviderInterface
{
    public function generateStructured(string $system, string $user, array $schema, string $schemaName): StructuredResult
    {
        $data = match ($schemaName) {
            'character_bible' => $this->characterBible($user),
            default => $this->analysis($user),
        };

        return new StructuredResult($data, 'fake-director', (int) (mb_strlen($system.$user) / 3), 1500, 'fake-'.md5($user));
    }

    private function analysis(string $user): array
    {
        preg_match('/<story>(.*?)<\/story>/su', $user, $match);
        $story = trim($match[1] ?? $user);
        preg_match('/<max_scenes>(\d+)<\/max_scenes>/', $user, $max);
        $maxScenes = (int) ($max[1] ?? 12);

        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $story))));
        $groups = array_chunk($paragraphs, max(1, (int) ceil(count($paragraphs) / max(1, $maxScenes))));

        $scenes = [];
        foreach ($groups as $index => $group) {
            $text = implode("\n", $group);
            $dialogues = [];
            if (preg_match_all('/«([^»]+)»/u', $text, $quotes)) {
                foreach ($quotes[1] as $line) {
                    $dialogues[] = [
                        'character_id' => 'hero',
                        'text' => trim($line),
                        'text_en' => null,
                        'emotion' => str_contains($line, '؟') ? 'worried' : 'happy',
                        'energy' => 'medium',
                        'pace' => 'normal',
                        'delivery' => 'speaking warmly',
                    ];
                }
            }
            $narration = trim(preg_replace('/\s*«[^»]+»\s*/u', ' ', $text));

            $scenes[] = [
                'scene_number' => $index + 1,
                'title' => 'صحنه '.($index + 1),
                'location_id' => 'main_place',
                'time_of_day' => $index === count($groups) - 1 ? 'evening' : 'morning',
                'duration_estimate' => max(6, min(30, (int) round(mb_strlen($text) / 12))),
                'narration' => $narration,
                'narration_en' => null,
                'characters_present' => [['character_id' => 'hero', 'action' => 'walks and looks around curiously', 'position' => 'center']],
                'dialogues' => $dialogues,
                'visual' => [
                    'description' => 'The hero in a gentle storybook setting, moment '.($index + 1).'.',
                    'shot_type' => $index === 0 ? 'wide establishing shot' : 'medium shot',
                    'camera_angle' => 'eye level',
                    'lighting' => 'soft warm daylight',
                    'mood' => 'gentle and curious',
                    'composition' => 'rule of thirds, character on the right third',
                    'character_actions' => ['hero walks forward'],
                    'image_prompt' => 'The hero walking through a calm, friendly landscape, scene '.($index + 1),
                    'recommended_motion' => ['zoom_in', 'pan_left', 'zoom_out', 'pan_right'][$index % 4],
                ],
                'audio' => [
                    'ambience' => 'soft outdoor ambience with a light breeze',
                    'sound_effects' => $index === 0 ? [[
                        'description' => 'Birds chirping',
                        'prompt' => 'gentle birds chirping in a peaceful forest morning',
                        'timing' => 'start',
                    ]] : [],
                    'music_mood' => 'calm',
                ],
                'continuity' => ['props' => ['red backpack'], 'outfit_changes' => [], 'weather' => 'sunny', 'notes' => ''],
            ];
        }

        return [
            'story' => [
                'title' => 'داستان آزمایشی',
                'summary' => mb_substr($story, 0, 120),
                'moral' => 'Kindness and curiosity',
                'tone' => 'warm',
                'estimated_duration_seconds' => array_sum(array_column($scenes, 'duration_estimate')),
                'visual_style_bible' => 'Soft rounded shapes, warm pastel palette, gentle textures, no scary elements.',
                'color_palette' => ['warm yellow', 'soft green', 'sky blue'],
                'music_theme' => 'gentle acoustic lullaby',
            ],
            'safety' => ['overall' => 'safe', 'flags' => []],
            'characters' => [[
                'id' => 'hero',
                'name' => 'قهرمان کوچولو',
                'role' => 'protagonist',
                'is_major' => true,
                'age_group' => 'child',
                'gender' => null,
                'personality' => 'Curious, playful, slightly shy.',
                'visual_description' => 'Small white rabbit with large expressive brown eyes and short ears with pink interior.',
                'clothing' => 'Yellow shirt, blue shorts.',
                'hair' => 'Soft white fur.',
                'face' => 'Round face, pink nose, gentle smile.',
                'colors' => ['white', 'yellow', 'blue', 'red'],
                'height_relation' => 'Smallest character, knee-high to adults.',
                'special_features' => 'Small red backpack.',
                'voice_description' => 'Playful, energetic, cute cartoon character voice.',
            ]],
            'locations' => [[
                'id' => 'main_place',
                'name' => 'جنگل سبز',
                'description' => 'A friendly green forest.',
                'visual_description' => 'Tall rounded trees, soft grass, wildflowers, dappled light.',
                'default_time_of_day' => 'morning',
            ]],
            'scenes' => $scenes,
        ];
    }

    private function characterBible(string $user): array
    {
        preg_match('/<name>(.*?)<\/name>/su', $user, $name);

        return [
            'personality' => 'Curious and kind.',
            'visual_description' => 'Refined: small white rabbit, big brown eyes, pink-lined ears.',
            'clothing' => 'Yellow shirt, blue shorts.',
            'hair' => 'Soft white fur.',
            'face' => 'Round face, pink nose.',
            'colors' => ['white', 'yellow', 'blue'],
            'height_relation' => 'Small.',
            'special_features' => 'Red backpack.',
            'voice_description' => 'Bright, playful child-like voice for '.($name[1] ?? 'character').'.',
        ];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-director';
    }
}
