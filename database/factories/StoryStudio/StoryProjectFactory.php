<?php

namespace Database\Factories\StoryStudio;

use App\Enums\StoryStudio\AspectRatio;
use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\NarrationStyle;
use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\Resolution;
use App\Enums\StoryStudio\StoryCategory;
use App\Enums\StoryStudio\TargetAge;
use App\Enums\StoryStudio\VisualStyle;
use App\Models\StoryStudio\StoryProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryProject>
 */
class StoryProjectFactory extends Factory
{
    protected $model = StoryProject::class;

    public function definition(): array
    {
        return [
            'title' => 'خرگوش کوچولو و '.fake()->randomElement(['جنگل مه‌آلود', 'رنگین‌کمان', 'دوست تازه', 'ماه نقره‌ای']),
            'original_text' => "روزی روزگاری در جنگلی سبز، خرگوش کوچولویی زندگی می‌کرد که خیلی کنجکاو بود.\nیک روز صبح او کوله‌پشتی قرمزش را برداشت و به دنبال مادرش رفت.\nخرگوش صدا زد: «مامان! کجایی؟»",
            'language' => 'fa',
            'target_age' => fake()->randomElement(TargetAge::cases()),
            'category' => fake()->randomElement(StoryCategory::cases()),
            'visual_style' => VisualStyle::ChildrensBook,
            'narration_style' => NarrationStyle::Warm,
            'aspect_ratio' => AspectRatio::Landscape,
            'resolution' => Resolution::FullHD,
            'mode' => GenerationMode::Manual,
        ];
    }

    public function status(ProjectStatus $status, int $progress = 0): static
    {
        return $this->afterMaking(function (StoryProject $project) use ($status, $progress) {
            $project->forceFill(['status' => $status, 'progress' => $progress]);
        });
    }
}
