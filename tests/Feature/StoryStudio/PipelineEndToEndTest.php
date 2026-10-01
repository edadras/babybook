<?php

namespace Tests\Feature\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\Resolution;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\StoryPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PipelineEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public const STORY = "روزی روزگاری در جنگلی سبز، خرگوش کوچولویی زندگی می‌کرد که خیلی کنجکاو بود.\n"
        ."یک روز صبح او کوله‌پشتی قرمزش را برداشت و به دنبال مادرش رفت. خرگوش صدا زد: «مامان! کجایی؟»\n"
        .'پرنده‌ها از بالای درخت‌ها پرواز کردند و خرگوش لبخند زد.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_auto_mode_produces_the_complete_story_package(): void
    {
        $project = StoryProject::factory()->create([
            'original_text' => self::STORY,
            'mode' => 'auto',
            'resolution' => Resolution::HD,
        ]);

        app(StoryPipelineService::class)->start($project, costConfirmed: true);
        $project->refresh();

        $this->assertNull($project->last_error, (string) $project->last_error);
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertSame(100, $project->progress);
        $this->assertSame(3, $project->scenes()->count());

        foreach ([AssetType::FinalVideo, AssetType::FinalAudio, AssetType::SubtitleSrt, AssetType::SubtitleVtt, AssetType::Cover, AssetType::AssetsZip] as $type) {
            $asset = $project->latestAsset($type);
            $this->assertNotNull($asset, $type->value.' missing');
            Storage::disk('local')->assertExists($asset->file_path);
        }

        $video = $project->latestAsset(AssetType::FinalVideo);
        $this->assertSame(1280, $video->width);
        $this->assertGreaterThan(3000, $video->duration_ms);
        $this->assertStringContainsString('-->', Storage::disk('local')->get($project->latestAsset(AssetType::SubtitleSrt)->file_path));

        // Each hero reference view, one image per scene, one narration per scene.
        $hero = $project->characters()->where('key', 'hero')->first();
        $this->assertTrue($hero->character_locked);
        $this->assertNotNull($hero->voiceProfile?->provider_voice_id);
        $this->assertSame(3, $hero->referenceAssets()->count());
        $this->assertSame(3, $project->assets()->where('type', AssetType::SceneImage)->count());
        $this->assertSame(3, $project->audioAssets()->where('type', AudioAssetType::Narration)->count());
        $this->assertSame(0, $project->generationJobs()->where('status', '!=', GenerationStatus::Succeeded)->count());
    }

    public function test_manual_mode_stops_at_each_approval_gate(): void
    {
        $project = StoryProject::factory()->create(['original_text' => self::STORY, 'mode' => 'manual', 'resolution' => Resolution::HD]);
        $pipeline = app(StoryPipelineService::class);

        $pipeline->start($project, costConfirmed: true);
        $this->assertSame('analyze', $project->fresh()->current_stage->value);
        $this->assertSame(0, $project->assets()->count(), 'no images before script approval');

        $pipeline->approve($project->fresh(), 'script');
        $this->assertSame('character_references', $project->fresh()->current_stage->value);

        $pipeline->approve($project->fresh(), 'characters');
        $project->refresh();
        $this->assertSame('voice_casting', $project->current_stage->value);
        $hero = $project->characters()->where('key', 'hero')->first();
        $this->assertTrue($hero->character_locked);
        $this->assertSame(3, $hero->voiceCandidates()->count(), 'candidates designed, none auto-accepted');
        $this->assertNull($hero->voice_profile_id);
    }
}
