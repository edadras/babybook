<?php

namespace Database\Factories\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryScene>
 */
class StorySceneFactory extends Factory
{
    protected $model = StoryScene::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 500);

        return [
            'project_id' => StoryProject::factory(),
            'scene_number' => $number,
            'sort_order' => $number,
            'title' => 'صحنه '.$number,
            'time_of_day' => 'morning',
            'duration_estimate_ms' => 12000,
            'narration_original' => 'خرگوش وارد جنگل شد و پرنده‌ها از بالای درخت‌ها پرواز کردند.',
            'visual' => [
                'description' => 'The little rabbit steps into a sunlit forest as birds take off from the treetops.',
                'shot_type' => 'wide',
                'camera_angle' => 'eye level',
                'lighting' => 'soft morning light',
                'character_actions' => ['rabbit walks forward, looking up in wonder'],
            ],
            'audio_plan' => ['ambience' => 'forest morning', 'music_mood' => 'adventure'],
        ];
    }
}
