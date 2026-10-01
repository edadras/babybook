<?php

namespace Tests\Feature\StoryStudio;

use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\Resolution;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryPronunciation;
use App\Services\StoryStudio\PronunciationDictionaryService;
use App\Services\StoryStudio\StoryPipelineService;
use App\Services\StoryStudio\VoiceDirectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PronunciationAndVoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pronunciation_rules_respect_word_boundaries(): void
    {
        $project = StoryProject::factory()->create();
        StoryPronunciation::create(['word' => 'بندر لنگه', 'pronunciation' => 'بَندَرِ لِنگه', 'type' => 'place']);
        StoryPronunciation::create(['project_id' => $project->id, 'word' => 'نیلو', 'pronunciation' => 'نی‌لو', 'type' => 'character_name']);

        $service = app(PronunciationDictionaryService::class);

        $this->assertSame('نی‌لو به بَندَرِ لِنگه رفت.', $service->normalize('نیلو به بندر لنگه رفت.', $project));
        $this->assertSame('نیلوفر آمد.', $service->normalize('نیلوفر آمد.', $project), 'must not touch longer words');
        $this->assertSame('کتاب یک', $service->normalize('كتاب يك', $project), 'Arabic letters are unified');
    }

    public function test_voice_director_turns_direction_into_tags_without_touching_text(): void
    {
        $dialogue = new StoryDialogue(['text_original' => 'مامان! کجایی؟', 'emotion' => 'worried', 'pace' => 'slightly_fast', 'delivery' => 'calling from a distance']);
        $director = app(VoiceDirectorService::class);
        $tags = $director->tagsForDialogue($dialogue);

        $this->assertSame(['worried', 'shouting', 'quickly'], $tags);
        $this->assertSame('[worried] [shouting] [quickly] مامان! کجایی؟', $director->applyTags('مامان! کجایی؟', $tags, true));
        $this->assertSame('مامان! کجایی؟', $director->applyTags('مامان! کجایی؟', $tags, false));
    }

    public function test_manual_voice_selection_is_reused_for_every_line(): void
    {
        Storage::fake('local');
        config(['story-studio.access.allow_guests' => true]);
        $project = StoryProject::factory()->create(['original_text' => PipelineEndToEndTest::STORY, 'mode' => 'manual', 'resolution' => Resolution::HD]);
        $pipeline = app(StoryPipelineService::class);
        $pipeline->start($project, true);
        $pipeline->approve($project->fresh(), 'script');
        $pipeline->approve($project->fresh(), 'characters');

        foreach ($project->characters()->get() as $character) {
            $candidate = $character->voiceCandidates()->first();
            $this->assertNotNull($candidate, $character->name.' has candidates');
            $this->post(route('story-studio.projects.voices.select', [$project, $candidate]))->assertRedirect();
        }

        $pipeline->approve($project->fresh(), 'voices');
        $pipeline->approve($project->fresh(), 'storyboard');
        $pipeline->approve($project->fresh(), 'review');

        $hero = $project->characters()->where('key', 'hero')->first();
        $voiceId = $hero->voiceProfile->provider_voice_id;
        $this->assertTrue($hero->voiceProfile->locked);

        $dialogueVoices = $project->audioAssets()->where('type', AudioAssetType::Dialogue)->where('character_id', $hero->id)->pluck('voice_id')->unique();
        $this->assertSame([$voiceId], $dialogueVoices->values()->all());
        $this->assertSame('completed', $project->fresh()->status->value);
    }
}
