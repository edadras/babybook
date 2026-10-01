<?php

namespace Tests\Unit\StoryStudio;

use App\Enums\StoryStudio\AspectRatio;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\Resolution;
use App\Enums\StoryStudio\VisualStyle;
use App\Support\StoryStudio\Fa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumsAndFormattingTest extends TestCase
{
    public static function frames(): array
    {
        return [
            ['16:9', '1080p', [1920, 1080]],
            ['16:9', '720p', [1280, 720]],
            ['9:16', '1080p', [1080, 1920]],
            ['1:1', '720p', [720, 720]],
        ];
    }

    #[DataProvider('frames')]
    public function test_frame_dimensions(string $ratio, string $resolution, array $expected): void
    {
        $this->assertSame($expected, AspectRatio::from($ratio)->dimensions(Resolution::from($resolution)));
    }

    public function test_every_status_has_a_persian_label_and_tone(): void
    {
        $this->assertCount(11, ProjectStatus::cases());

        foreach (ProjectStatus::cases() as $status) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $status->label());
            $this->assertContains($status->tone(), ['neutral', 'info', 'working', 'success', 'danger']);
        }

        $this->assertTrue(ProjectStatus::GeneratingImages->isWorking());
        $this->assertFalse(ProjectStatus::Completed->isWorking());
    }

    public function test_pipeline_stages_are_ordered_and_weighted(): void
    {
        $this->assertSame(PipelineStage::Analyze, PipelineStage::cases()[0]);
        $this->assertSame(PipelineStage::FinalRender, PipelineStage::cases()[10]);
        $this->assertSame(100, array_sum(array_map(fn (PipelineStage $s) => $s->weight(), PipelineStage::cases())));
    }

    public function test_only_custom_style_lacks_a_prompt_fragment(): void
    {
        foreach (VisualStyle::cases() as $style) {
            $style === VisualStyle::Custom
                ? $this->assertNull($style->promptFragment())
                : $this->assertNotEmpty($style->promptFragment());
        }
    }

    public function test_persian_digits_and_durations(): void
    {
        $this->assertSame('۱۲۳', Fa::digits(123));
        $this->assertSame('۳:۰۵', Fa::duration(185_000));
        $this->assertSame('۰:۱۲', Fa::duration(11_600));
        $this->assertSame('—', Fa::duration(null));
    }
}
