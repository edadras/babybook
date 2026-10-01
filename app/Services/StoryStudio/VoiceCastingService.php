<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\VoiceProfileStatus;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryVoiceProfile;
use App\Services\StoryStudio\Contracts\VoiceDesignProviderInterface;
use Illuminate\Support\Facades\DB;

/**
 * Voice casting: design several candidate voices from the AI voice description,
 * let the administrator pick one, then reuse that voice id everywhere.
 */
class VoiceCastingService
{
    public function __construct(
        private readonly VoiceDesignProviderInterface $designer,
        private readonly GenerationGuard $guard,
        private readonly AssetStorageService $storage,
        private readonly DependencyInvalidator $invalidator,
        private readonly AudioGenerationService $audio,
    ) {}

    /**
     * @return list<StoryVoiceProfile>
     */
    public function designCandidates(StoryCharacter $character, string $nonce): array
    {
        $project = $character->project;
        $description = $this->description($character);
        $sample = $this->sampleText($character);
        $key = GenerationGuard::key('voice-design', $character->getKey(), md5($description.$sample), $nonce);

        $run = $this->guard->run(
            $project, $key, PipelineStage::VoiceCasting, 'voice.design', $character, $this->designer->name(), $this->designer->model(),
            CostCategory::ElevenLabsVoiceDesign, ['description' => mb_substr($description, 0, 200)],
            function () use ($character, $project, $description, $sample) {
                $profiles = [];
                foreach ($this->designer->design($description, $sample) as $index => $preview) {
                    $audio = $this->storage->storeAudio($project, AudioAssetType::VoicePreview, 'voice-candidate:'.$preview->generatedVoiceId,
                        $character->key.'_candidate_'.($index + 1), $preview->audioBytes, 'mp3', [
                            'character_id' => $character->getKey(), 'provider' => $this->designer->name(), 'model' => $this->designer->model(),
                            'text' => $sample, 'duration_ms' => $preview->durationMs,
                        ]);
                    $profile = $project->voiceProfiles()->create([
                        'character_id' => $character->getKey(),
                        'provider' => $this->designer->name(),
                        'name' => $character->name.' — نمونه '.($index + 1),
                        'description' => $description,
                        'source' => 'designed',
                        'generated_voice_id' => $preview->generatedVoiceId,
                        'status' => VoiceProfileStatus::Candidate,
                    ]);
                    $profile->forceFill(['preview_audio_id' => $audio->getKey()])->save();
                    $profiles[] = $profile;
                }

                return new GenerationOutcome($profiles, 1, 0, 'calls', null, ['profile_ids' => array_map(fn ($p) => $p->getKey(), $profiles)]);
            },
        );

        // Candidates are ready for review; an already-cast character stays approved.
        $character->forceFill(['voice_status' => $character->voice_profile_id ? AssetStatus::Approved : AssetStatus::Ready])->save();

        return $run->result ?? StoryVoiceProfile::query()->whereIn('id', $run->ledger->response_summary['profile_ids'] ?? [])->get()->all();
    }

    /**
     * Approve a candidate: persist designed voices permanently and assign it to the character.
     */
    public function select(StoryVoiceProfile $profile): StoryVoiceProfile
    {
        $character = $profile->character;
        abort_if($character->voiceProfile?->locked && $character->voice_profile_id !== $profile->getKey(), 409, 'صدای این شخصیت قفل است.');

        if (! $profile->provider_voice_id && $profile->generated_voice_id) {
            $voiceId = $this->designer->createVoice($profile->generated_voice_id, $character->project->title.' — '.$character->name, (string) $profile->description);
            $profile->forceFill(['provider_voice_id' => $voiceId])->save();
        }

        $previous = $character->voice_profile_id;

        DB::transaction(function () use ($profile, $character) {
            $character->voiceCandidates()->whereKeyNot($profile->getKey())->where('status', VoiceProfileStatus::Selected)
                ->update(['status' => VoiceProfileStatus::Candidate->value]);
            $profile->forceFill(['status' => VoiceProfileStatus::Selected])->save();
            $character->forceFill(['voice_profile_id' => $profile->getKey(), 'voice_status' => AssetStatus::Approved])->save();
        });

        if ($previous && $previous !== $profile->getKey()) {
            $this->invalidator->characterVoiceChanged($character->fresh());
        }

        return $profile;
    }

    /**
     * Assign an existing voice from the provider's library (or any known voice id).
     */
    public function assignLibraryVoice(StoryCharacter $character, string $voiceId, ?string $name = null): StoryVoiceProfile
    {
        $profile = $character->project->voiceProfiles()->create([
            'character_id' => $character->getKey(),
            'provider' => config('story-studio.providers.speech'),
            'provider_voice_id' => $voiceId,
            'name' => $name ?: $voiceId,
            'description' => $character->voice_description,
            'source' => 'library',
            'status' => VoiceProfileStatus::Candidate,
        ]);

        return $this->select($profile);
    }

    /**
     * Automatic mode: use a configured default voice, otherwise design and pick the first candidate.
     */
    public function autoCast(StoryCharacter $character, string $nonce): void
    {
        if ($character->voice_profile_id) {
            return;
        }

        $defaults = (array) config('story-studio.elevenlabs.default_voices', []);
        $default = $character->is_narrator ? config('story-studio.elevenlabs.default_narrator_voice_id') : ($defaults[$character->key] ?? null);

        if ($default) {
            $this->assignLibraryVoice($character, $default, 'پیش‌فرض');

            return;
        }

        $candidates = $this->designCandidates($character, $nonce);
        if ($candidates !== []) {
            $profile = $this->select($candidates[0]);
            $profile->forceFill(['settings' => array_merge($profile->settings ?? [], ['auto_selected' => true])])->save();
        }
    }

    public function preview(StoryVoiceProfile $profile, string $nonce): void
    {
        abort_unless($profile->provider_voice_id, 422, 'این صدا هنوز شناسه ندارد.');
        $asset = $this->audio->voicePreview($profile->character, $profile->provider_voice_id, $this->sampleText($profile->character), $nonce);
        if ($asset) {
            $profile->forceFill(['preview_audio_id' => $asset->getKey()])->save();
        }
    }

    public function lock(StoryCharacter $character, bool $locked = true): void
    {
        abort_unless($character->voiceProfile, 422, 'ابتدا یک صدا انتخاب کنید.');
        $character->voiceProfile->forceFill(['locked' => $locked])->save();
    }

    public function description(StoryCharacter $character): string
    {
        $language = $character->project->language === 'fa' ? 'Speaks natural, fluent Persian (Farsi).' : 'Speaks natural English.';

        return trim(($character->voice_description ?: 'Friendly expressive voice for a children\'s story character.').' '.$language
            .($character->age_group ? ' Age: '.$character->age_group.'.' : ''));
    }

    public function sampleText(StoryCharacter $character): string
    {
        $lines = $character->is_narrator
            ? $character->project->scenes()->limit(3)->pluck('narration_original')->filter()->all()
            : $character->dialogues()->limit(4)->pluck('text_original')->all();

        $text = trim(implode(' ', $lines));
        if (mb_strlen($text) < 100) {
            $text = trim($text.' '.mb_substr($character->project->original_text, 0, 300));
        }

        return mb_substr($text, 0, 600);
    }
}
