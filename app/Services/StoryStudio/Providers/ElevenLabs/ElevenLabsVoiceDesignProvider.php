<?php

namespace App\Services\StoryStudio\Providers\ElevenLabs;

use App\Services\StoryStudio\Contracts\VoiceDesignProviderInterface;
use App\Services\StoryStudio\Data\VoicePreview;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\ProviderHttp;

/**
 * ElevenLabs Voice Design: description → several previews → persist the chosen one.
 */
class ElevenLabsVoiceDesignProvider implements VoiceDesignProviderInterface
{
    public function __construct(private readonly ElevenLabsClient $client) {}

    public function design(string $description, string $sampleText): array
    {
        $startedAt = microtime(true);
        $payload = [
            'voice_description' => mb_substr($description, 0, 1000),
            'model_id' => $this->model(),
        ];

        // The API requires 100–1000 characters of sample text; otherwise let it auto-generate.
        if (mb_strlen($sampleText) >= 100) {
            $payload['text'] = mb_substr($sampleText, 0, 1000);
        } else {
            $payload['auto_generate_text'] = true;
        }

        $response = $this->client->request(180)
            ->post('/text-to-voice/design?output_format='.$this->client->outputFormat(), $payload);
        ProviderHttp::check('elevenlabs', 'text-to-voice.design', $response, $startedAt);

        $previews = [];
        foreach ($response->json('previews', []) as $preview) {
            $previews[] = new VoicePreview(
                (string) $preview['generated_voice_id'],
                base64_decode((string) $preview['audio_base_64']),
                'audio/mpeg',
                isset($preview['duration_secs']) ? (int) round($preview['duration_secs'] * 1000) : null,
            );
        }

        if ($previews === []) {
            throw new ProviderException('ElevenLabs هیچ نمونه صدایی برنگرداند.', 'elevenlabs', 200, true, 'empty_previews');
        }

        return $previews;
    }

    public function createVoice(string $generatedVoiceId, string $name, string $description): string
    {
        $startedAt = microtime(true);
        $response = $this->client->request(60)->post('/text-to-voice', [
            'voice_name' => mb_substr($name, 0, 100),
            'voice_description' => mb_substr($description, 0, 1000),
            'generated_voice_id' => $generatedVoiceId,
        ]);
        ProviderHttp::check('elevenlabs', 'text-to-voice.create', $response, $startedAt);

        return (string) $response->json('voice_id');
    }

    public function name(): string
    {
        return 'elevenlabs';
    }

    public function model(): string
    {
        return (string) config('story-studio.elevenlabs.voice_design_model');
    }
}
