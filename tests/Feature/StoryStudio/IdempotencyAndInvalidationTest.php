<?php

namespace Tests\Feature\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\Resolution;
use App\Jobs\StoryStudio\GenerateSceneImageJob;
use App\Models\StoryStudio\StoryProject;
use App\Models\User;
use App\Services\StoryStudio\Contracts\ImageGenerationProviderInterface;
use App\Services\StoryStudio\Data\ImageResult;
use App\Services\StoryStudio\Providers\Fake\FakeImageProvider;
use App\Services\StoryStudio\StoryPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdempotencyAndInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private StoryProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['story-studio.access.allow_guests' => true]);

        $this->project = StoryProject::factory()->create([
            'original_text' => PipelineEndToEndTest::STORY,
            'mode' => 'auto',
            'resolution' => Resolution::HD,
        ]);
        app(StoryPipelineService::class)->start($this->project, costConfirmed: true);
        $this->project->refresh();
    }

    public function test_a_duplicated_job_never_generates_or_charges_twice(): void
    {
        $counter = new class extends FakeImageProvider
        {
            public int $calls = 0;

            public function generate(string $prompt, int $width, int $height, array $referenceImagePaths = []): ImageResult
            {
                $this->calls++;

                return parent::generate($prompt, $width, $height, $referenceImagePaths);
            }
        };
        $this->app->instance(ImageGenerationProviderInterface::class, $counter);

        $scene = $this->project->scenes()->first();
        $before = $scene->assets()->where('type', AssetType::SceneImage)->count();
        $ledgerBefore = $this->project->generationJobs()->count();

        // The same job (same nonce) delivered twice by the queue.
        (new GenerateSceneImageJob($this->project->id, $scene->id, 'retry-me'))->handle();
        (new GenerateSceneImageJob($this->project->id, $scene->id, 'retry-me'))->handle();

        $this->assertSame(1, $counter->calls, 'provider must be called once');
        $this->assertSame($before + 1, $scene->assets()->where('type', AssetType::SceneImage)->count());
        $this->assertSame($ledgerBefore + 1, $this->project->generationJobs()->count());
        $this->assertSame(GenerationStatus::Succeeded, $this->project->generationJobs()->latest('created_at')->first()->status);
    }

    public function test_regenerating_an_image_keeps_previous_versions(): void
    {
        $scene = $this->project->scenes()->first();

        $this->post(route('story-studio.projects.scenes.image', [$this->project, $scene]))->assertRedirect();

        $versions = $scene->assets()->where('type', AssetType::SceneImage)->orderBy('version')->get();
        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertTrue($versions[1]->selected);
        $this->assertFalse($versions[0]->fresh()->selected);
        Storage::disk('local')->assertExists($versions[0]->file_path);

        // Selecting the old version only invalidates the scene clip and final render.
        $this->post(route('story-studio.projects.assets.select', [$this->project, $versions[0]]))->assertRedirect();
        $this->assertTrue($versions[0]->fresh()->selected);
        $this->assertSame(AssetStatus::Stale, $scene->fresh()->workState('video'));
        $this->assertSame(AssetStatus::Ready, $scene->fresh()->workState('mix'));
    }

    public function test_editing_a_dialogue_only_invalidates_its_dependents(): void
    {
        $scene = $this->project->scenes()->whereHas('dialogues')->first();
        $other = $this->project->scenes()->whereKeyNot($scene->id)->first();
        $dialogue = $scene->dialogues()->first();
        $hero = $dialogue->character;
        $heroImages = $hero->referenceAssets()->count();

        $this->put(route('story-studio.projects.dialogues.update', [$this->project, $dialogue]), [
            'character_id' => $dialogue->character_id,
            'text_original' => 'مامان جان! کجایی؟',
            'emotion' => 'worried',
            'energy' => 'medium',
            'pace' => 'slightly_fast',
            'delivery' => 'calling from a distance',
        ])->assertRedirect();

        $this->assertSame(AssetStatus::Stale, $dialogue->fresh()->status);
        $scene->refresh();
        $this->assertSame(AssetStatus::Stale, $scene->workState('mix'));
        $this->assertSame(AssetStatus::Stale, $scene->workState('video'));
        $this->assertSame(AssetStatus::Ready, $scene->workState('image'));
        $this->assertSame(AssetStatus::Stale, $this->project->fresh()->workState('final'));
        $this->assertSame(AssetStatus::Ready, $other->fresh()->workState('mix'));
        $this->assertSame($heroImages, $hero->referenceAssets()->count());

        // Re-running regenerates exactly the stale chain.
        $imagesBefore = $this->project->assets()->where('type', AssetType::SceneImage)->count();
        app(StoryPipelineService::class)->start($this->project->fresh(), costConfirmed: true);
        $this->assertSame(AssetStatus::Ready, $this->project->fresh()->workState('final'));
        $this->assertSame($imagesBefore, $this->project->assets()->where('type', AssetType::SceneImage)->count());
        $this->assertSame(2, $dialogue->audioAssets()->count());
        $this->assertSame(2, $this->project->assets()->where('type', AssetType::FinalVideo)->count());
    }

    public function test_every_studio_tab_renders_for_a_finished_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['show', 'characters', 'voices', 'pronunciation', 'storyboard', 'sound', 'timeline', 'render', 'export', 'costs'] as $tab) {
            $route = $tab === 'show' ? 'story-studio.projects.show' : 'story-studio.projects.'.$tab;
            $this->get(route($route, $this->project))->assertOk()->assertSee($this->project->title);
        }

        $this->get(route('story-studio.index'))->assertOk()->assertSee('دانلود');

        $video = $this->project->latestAsset(AssetType::FinalVideo);
        $this->get(route('story-studio.files.show', ['asset', $video->id]))->assertOk()->assertHeader('content-type', 'video/mp4');
        $this->get(route('story-studio.files.show', ['asset', $video->id, 'download' => 1]))->assertOk()->assertDownload();
    }

    public function test_files_are_not_served_to_unauthorised_users(): void
    {
        config(['story-studio.access.allow_guests' => false]);
        $video = $this->project->latestAsset(AssetType::FinalVideo);

        $this->get(route('story-studio.files.show', ['asset', $video->id]))->assertUnauthorized();
    }

    public function test_scene_split_reorder_and_delete(): void
    {
        $this->project->scenes()->first()->update(['narration_original' => 'جمله اول اینجاست. جمله دوم آنجاست. جمله سوم هم هست.']);
        $first = $this->project->scenes()->first();

        $this->post(route('story-studio.projects.scenes.split', [$this->project, $first]))->assertRedirect();
        $scenes = $this->project->scenes()->get();
        $this->assertCount(4, $scenes);
        $this->assertSame('جمله اول اینجاست. جمله دوم آنجاست.', $scenes[0]->narration_original);
        $this->assertSame('جمله سوم هم هست.', $scenes[1]->narration_original);
        $this->assertSame([1, 2, 3, 4], $scenes->pluck('scene_number')->all());

        $order = $scenes->pluck('id')->reverse()->values()->all();
        $this->postJson(route('story-studio.projects.scenes.reorder', $this->project), ['order' => $order])->assertOk();
        $this->assertSame($order, $this->project->scenes()->pluck('id')->all());

        $this->delete(route('story-studio.projects.scenes.destroy', [$this->project, $this->project->scenes()->first()]))->assertRedirect();
        $this->assertSame([1, 2, 3], $this->project->scenes()->pluck('scene_number')->all());
    }
}
