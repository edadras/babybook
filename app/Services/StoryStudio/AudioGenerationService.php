<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\SoundKind;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Models\StoryStudio\StorySoundEffect;
use App\Services\StoryStudio\Contracts\SoundEffectProviderInterface;
use App\Services\StoryStudio\Contracts\SpeechProviderInterface;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\Ffmpeg;

/**
 * Narration, character dialogue and sound effects. Each line is its own
 * versioned audio asset; voices always come from the character's locked
 * voice profile so the same voice id is reused across the whole project.
 */
class AudioGenerationService
{
    public function __construct(
        private readonly SpeechProviderInterface $speech,
        private readonly SoundEffectProviderInterface $effects,
        private readonly GenerationGuard $guard,
        private readonly AssetStorageService $storage,
        private readonly PronunciationDictionaryService $pronunciation,
        private readonly VoiceDirectorService $voiceDirector,
        private readonly DependencyInvalidator $invalidator,
        private readonly Ffmpeg $ffmpeg,
    ) {}

    public function narration(StoryScene $scene, string $nonce): ?StoryAudioAsset
    {
        $project = $scene->project;
        $text = trim((string) $scene->narration_original);
        if ($text === '') {
            return null;
        }

        $narrator = $project->narrator();
        $voiceId = $this->voiceIdFor($narrator, $project);
        $ttsText = $this->pronunciation->normalize($text, $project);
        if ($ttsText !== $scene->narration_tts) {
            $scene->forceFill(['narration_tts' => $ttsText])->save();
        }
        $spoken = $this->voiceDirector->applyTags($ttsText, $this->voiceDirector->tagsForNarration($project), $this->speech->supportsAudioTags());

        $asset = $this->tts(
            $project, $scene, $spoken, $voiceId, $this->voiceDirector->voiceSettings('low'),
            AudioAssetType::Narration, $scene->slot('narration'), sprintf('scene_%02d_narrator', $scene->scene_number),
            CostCategory::ElevenLabsNarration, $nonce,
            ['scene_id' => $scene->getKey(), 'character_id' => $narrator?->getKey(), 'voice_profile_id' => $narrator?->voice_profile_id, 'text' => $text, 'tts_text' => $spoken],
        );
        $this->invalidator->sceneOutputsChanged($scene);

        return $asset;
    }

    public function dialogue(StoryDialogue $dialogue, string $nonce): ?StoryAudioAsset
    {
        $scene = $dialogue->scene;
        $project = $scene->project;
        $character = $dialogue->character;
        $voiceId = $this->voiceIdFor($character, $project);

        $ttsText = $this->pronunciation->normalize($dialogue->text_original, $project);
        $tags = $this->voiceDirector->tagsForDialogue($dialogue);
        $spoken = $this->voiceDirector->applyTags($ttsText, $tags, $this->speech->supportsAudioTags());
        $dialogue->forceFill(['text_tts' => $ttsText, 'provider_tags' => $tags])->save();

        $asset = $this->tts(
            $project, $dialogue, $spoken, $voiceId, $this->voiceDirector->voiceSettings($dialogue->energy),
            AudioAssetType::Dialogue, $dialogue->slot(), sprintf('scene_%02d_%s', $scene->scene_number, $character?->key ?? 'character'),
            CostCategory::ElevenLabsDialogue, $nonce,
            ['scene_id' => $scene->getKey(), 'dialogue_id' => $dialogue->getKey(), 'character_id' => $character?->getKey(), 'voice_profile_id' => $character?->voice_profile_id, 'text' => $dialogue->text_original, 'tts_text' => $spoken],
        );
        $this->invalidator->sceneOutputsChanged($scene);

        return $asset;
    }

    public function soundEffect(StorySoundEffect $effect, string $nonce): ?StoryAudioAsset
    {
        $scene = $effect->scene;
        $project = $scene->project;
        $prompt = (string) ($effect->prompt ?: $effect->description);
        $seconds = $effect->kind === SoundKind::Ambience ? 10.0 : ($effect->duration_ms ? $effect->duration_ms / 1000 : null);
        $key = GenerationGuard::key('sfx', $effect->getKey(), md5($prompt.'|'.$seconds), $nonce);
        $type = $effect->kind === SoundKind::Ambience ? AudioAssetType::Ambience : AudioAssetType::SoundEffect;

        $run = $this->guard->run(
            $project, $key, PipelineStage::SoundEffects, 'audio.sfx', $effect, $this->effects->name(), $this->effects->model(),
            CostCategory::ElevenLabsSoundEffects, ['prompt' => mb_substr($prompt, 0, 200), 'seconds' => $seconds],
            function () use ($project, $scene, $effect, $prompt, $seconds, $type) {
                $started = microtime(true);
                $result = $this->effects->generate($prompt, $seconds);
                $asset = $this->storeAudio($project, $type, $effect->slot(), sprintf('scene_%02d_%s', $scene->scene_number, $type->value), $result->bytes, [
                    'scene_id' => $scene->getKey(), 'sound_effect_id' => $effect->getKey(), 'provider' => $this->effects->name(),
                    'model' => $result->model, 'text' => $prompt, 'generation_time_ms' => (int) ((microtime(true) - $started) * 1000),
                    'actual_cost' => app(CostTracker::class)->price(CostCategory::ElevenLabsSoundEffects, 1),
                ]);

                return new GenerationOutcome($asset, 1, 0, 'generations', $result->requestId, ['asset_id' => $asset->getKey()]);
            },
        );

        $this->invalidator->soundEffectChanged($effect, false);

        return $this->resolve($run);
    }

    /**
     * Short sample read in the chosen voice, for the voice casting studio.
     */
    public function voicePreview(StoryCharacter $character, string $voiceId, string $sampleText, string $nonce): ?StoryAudioAsset
    {
        $project = $character->project;

        return $this->tts(
            $project, $character, $this->pronunciation->normalize($sampleText, $project), $voiceId, [],
            AudioAssetType::VoicePreview, 'voice:'.$character->getKey().':'.$voiceId, $character->key.'_voice_preview',
            $character->is_narrator ? CostCategory::ElevenLabsNarration : CostCategory::ElevenLabsDialogue, $nonce,
            ['character_id' => $character->getKey(), 'text' => $sampleText],
        );
    }

    private function voiceIdFor(?StoryCharacter $character, StoryProject $project): string
    {
        $voiceId = $character?->voiceProfile?->provider_voice_id;

        if (! $voiceId && $character && ! $character->is_narrator) {
            // Unvoiced minor characters borrow the narrator's voice rather than inventing a new one per line.
            $voiceId = $project->narrator()?->voiceProfile?->provider_voice_id;
        }

        $voiceId ??= config('story-studio.elevenlabs.default_narrator_voice_id');

        if (! $voiceId) {
            throw new ProviderException('برای «'.($character?->name ?? 'راوی').'» هنوز صدایی انتخاب نشده است.', $this->speech->name(), null, false, 'no_voice');
        }

        return $voiceId;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $attributes
     */
    private function tts($project, $target, string $text, string $voiceId, array $settings, AudioAssetType $type, string $slot, string $baseName, CostCategory $category, string $nonce, array $attributes): ?StoryAudioAsset
    {
        $key = GenerationGuard::key('tts', $slot, md5($text), $voiceId, $nonce);

        $run = $this->guard->run(
            $project, $key, $type === AudioAssetType::Narration ? PipelineStage::Audio : ($type === AudioAssetType::VoicePreview ? PipelineStage::VoiceCasting : PipelineStage::Audio),
            'audio.'.$type->value, $target, $this->speech->name(), $this->speech->model(), $category,
            ['characters' => mb_strlen($text), 'voice_id' => $voiceId],
            function () use ($project, $text, $voiceId, $settings, $type, $slot, $baseName, $attributes, $category) {
                $started = microtime(true);
                $result = $this->speech->synthesize($text, $voiceId, $settings);
                $characters = $result->characters ?: mb_strlen($text);
                $asset = $this->storeAudio($project, $type, $slot, $baseName, $result->bytes, array_merge($attributes, [
                    'provider' => $this->speech->name(), 'model' => $result->model, 'voice_id' => $voiceId,
                    'generation_time_ms' => (int) ((microtime(true) - $started) * 1000),
                    'actual_cost' => app(CostTracker::class)->price($category, $characters),
                    'metadata' => ['request_id' => $result->requestId, 'voice_settings' => $settings],
                ]));

                return new GenerationOutcome($asset, $characters, 0, 'characters', $result->requestId, ['asset_id' => $asset->getKey()]);
            },
        );

        return $this->resolve($run);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeAudio(StoryProject $project, AudioAssetType $type, string $slot, string $baseName, string $bytes, array $attributes): StoryAudioAsset
    {
        $asset = $this->storage->storeAudio($project, $type, $slot, $baseName, $bytes, 'mp3', $attributes);
        $asset->forceFill(['duration_ms' => $this->ffmpeg->durationMs($this->storage->localPath($asset))])->save();

        return $asset;
    }

    private function resolve(GenerationRun $run): ?StoryAudioAsset
    {
        $asset = $run->result ?? StoryAudioAsset::find($run->ledger->response_summary['asset_id'] ?? null);
        if ($asset) {
            $asset->generation_job_id ??= $run->ledger->getKey();
            $asset->save();
            if ($run->reused && ! $asset->selected) {
                $asset->select();
            }
        }

        return $asset;
    }
}
