<?php

namespace Tests\Unit\StoryStudio;

use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\SubtitleMode;
use App\Services\StoryStudio\SubtitleService;
use App\Services\StoryStudio\TimelineService;
use App\Services\StoryStudio\VideoRenderService;
use Tests\TestCase;

class MediaHelpersTest extends TestCase
{
    public function test_layout_overlaps_scenes_by_their_transitions(): void
    {
        $layout = app(TimelineService::class)->layout([10000, 8000, 6000], [600, 600, 600]);

        $this->assertSame([0, 9400, 16800], $layout['starts']);
        $this->assertSame(22800, $layout['total_ms']);
    }

    public function test_transitions_never_exceed_forty_percent_of_a_short_scene(): void
    {
        $layout = app(TimelineService::class)->layout([3000, 3000], [3000]);
        $this->assertSame([1200], $layout['transitions']);
    }

    public function test_subtitles_are_rtl_safe_and_well_formed(): void
    {
        $service = app(SubtitleService::class);
        $cues = $service->cues([[
            'start_ms' => 1000,
            'plan' => ['voice' => [[
                'start_ms' => 400, 'duration_ms' => 4000, 'text' => 'خرگوش صدا زد. مامان کجایی؟', 'text_en' => 'The rabbit called. Mom, where are you?',
            ]]],
        ]]);

        $this->assertCount(1, $cues);
        $this->assertSame(1400, $cues[0]['start']);

        $srt = $service->srt($cues, SubtitleMode::Bilingual);
        $this->assertStringContainsString('00:00:01,400 --> ', $srt);
        $this->assertStringContainsString("\u{200F}خرگوش", $srt);
        $this->assertStringContainsString('The rabbit called.', $srt);

        $vtt = $service->vtt($cues, SubtitleMode::Persian);
        $this->assertStringStartsWith('WEBVTT', $vtt);
        $this->assertStringContainsString('00:00:01.400 --> ', $vtt);

        $ass = $service->ass($cues, SubtitleMode::Persian, 1920, 1080, 42, 'Vazirmatn');
        $this->assertStringContainsString('Dialogue: 0,0:00:01.40,', $ass);
    }

    public function test_long_lines_are_chunked_for_mobile(): void
    {
        $chunks = app(SubtitleService::class)->chunk(str_repeat('کلمه‌ای طولانی ', 20));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(70, mb_strlen($chunk));
        }
        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_motion_is_subtle(): void
    {
        $render = app(VideoRenderService::class);
        $this->assertSame('1+0.08*on/250', $render->motionExpressions(ImageMotion::ZoomIn, 250)['z']);
        $this->assertSame('1', $render->motionExpressions(ImageMotion::None, 250)['z']);
        $this->assertStringContainsString('1-on/250', $render->motionExpressions(ImageMotion::PanLeft, 250)['x']);
    }
}
