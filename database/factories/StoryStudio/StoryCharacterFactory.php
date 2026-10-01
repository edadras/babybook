<?php

namespace Database\Factories\StoryStudio;

use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoryCharacter>
 */
class StoryCharacterFactory extends Factory
{
    protected $model = StoryCharacter::class;

    public function definition(): array
    {
        return [
            'project_id' => StoryProject::factory(),
            'key' => 'char_'.Str::lower(Str::random(6)),
            'name' => 'خرگوش کوچولو',
            'role' => 'protagonist',
            'is_major' => true,
            'personality' => 'Curious, playful, slightly shy.',
            'visual_description' => 'Small white rabbit, large expressive brown eyes, short ears with pink interior.',
            'clothing' => 'Yellow shirt, blue shorts, small red backpack.',
            'colors' => ['white', 'yellow', 'blue', 'red'],
            'voice_description' => 'Playful, energetic, cute cartoon character voice.',
        ];
    }

    public function narrator(): static
    {
        return $this->state([
            'key' => 'narrator',
            'name' => 'راوی',
            'role' => 'narrator',
            'is_narrator' => true,
            'visual_description' => null,
            'clothing' => null,
            'voice_description' => "Warm Persian storyteller, gentle, calm, expressive, suited to children's bedtime stories.",
        ]);
    }
}
