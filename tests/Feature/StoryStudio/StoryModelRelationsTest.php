<?php

namespace Tests\Feature\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\VoiceProfileStatus;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryPronunciation;
use App\Models\StoryStudio\StoryScene;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_graph_is_connected(): void
    {
        $project = StoryProject::factory()->create();
        $rabbit = StoryCharacter::factory()->for($project, 'project')->create(['key' => 'rabbit']);
        $narrator = StoryCharacter::factory()->narrator()->for($project, 'project')->create();
        $location = $project->locations()->create(['key' => 'forest', 'name' => 'جنگل']);
        $scene = StoryScene::factory()->for($project, 'project')->create(['location_id' => $location->id]);

        $scene->characters()->attach($rabbit, ['action' => 'walks in']);
        $dialogue = $scene->dialogues()->create([
            'character_id' => $rabbit->id,
            'text_original' => 'مامان! کجایی؟',
            'emotion' => 'worried',
            'pace' => 'slightly fast',
            'delivery' => 'calling from a distance',
        ]);
        $sfx = $scene->soundEffects()->create(['description' => 'Bird chirping']);

        $voice = $project->voiceProfiles()->create([
            'character_id' => $rabbit->id,
            'provider_voice_id' => 'voice_abc',
            'status' => VoiceProfileStatus::Selected,
        ]);
        $rabbit->voiceProfile()->associate($voice)->save();

        $this->assertCount(2, $project->characters);
        $this->assertTrue($narrator->is_narrator);
        $this->assertSame('forest', $scene->location->key);
        $this->assertSame('walks in', $scene->characters->first()->pivot->action);
        $this->assertTrue($dialogue->character->is($rabbit));
        $this->assertTrue($sfx->enabled);
        $this->assertSame('voice_abc', $rabbit->fresh()->voiceProfile->provider_voice_id);
        $this->assertCount(1, $rabbit->dialogues);
    }

    public function test_assets_are_versioned_and_never_overwritten(): void
    {
        $scene = StoryScene::factory()->create();
        $slot = $scene->imageSlot();

        $versions = collect(range(1, 3))->map(fn () => StoryAsset::create([
            'project_id' => $scene->project_id,
            'scene_id' => $scene->id,
            'type' => AssetType::SceneImage,
            'slot' => $slot,
            'version' => StoryAsset::nextVersion($scene->project_id, $slot),
            'status' => 'ready',
            'prompt' => 'prompt',
        ]));

        $this->assertSame([1, 2, 3], $versions->pluck('version')->all());
        $this->assertSame('scene_image_v2', $versions[1]->versionLabel());

        $versions[1]->select();
        $this->assertTrue($scene->selectedImage()->is($versions[1]));

        $versions[2]->select();
        $this->assertTrue($scene->selectedImage()->is($versions[2]));
        $this->assertSame(1, StoryAsset::forSlot($slot)->selected()->count());
        $this->assertSame(3, StoryAsset::forSlot($slot)->count());
    }

    public function test_duplicate_versions_are_rejected_by_the_database(): void
    {
        $scene = StoryScene::factory()->create();
        $attributes = [
            'project_id' => $scene->project_id,
            'scene_id' => $scene->id,
            'type' => AudioAssetType::Narration,
            'slot' => 'scene:'.$scene->id.':narration',
            'version' => 1,
        ];

        StoryAudioAsset::create($attributes);

        $this->expectException(QueryException::class);
        StoryAudioAsset::create($attributes);
    }

    public function test_pronunciations_combine_global_and_project_entries(): void
    {
        [$project, $other] = StoryProject::factory()->count(2)->create();

        StoryPronunciation::create(['word' => 'بندر لنگه', 'pronunciation' => 'بَندَرِ لِنگه', 'type' => 'place']);
        StoryPronunciation::create(['project_id' => $project->id, 'word' => 'نیلو', 'pronunciation' => 'نی‌لو', 'type' => 'character_name']);
        StoryPronunciation::create(['project_id' => $other->id, 'word' => 'دیگری', 'pronunciation' => 'دیگری']);

        $this->assertEqualsCanonicalizing(
            ['بندر لنگه', 'نیلو'],
            StoryPronunciation::applicableTo($project)->pluck('word')->all(),
        );
    }

    public function test_deleting_a_project_cascades_on_force_delete(): void
    {
        $scene = StoryScene::factory()->create();
        $project = $scene->project;

        $project->forceDelete();

        $this->assertDatabaseCount('story_scenes', 0);
        $this->assertDatabaseCount('story_project_settings', 0);
    }
}
