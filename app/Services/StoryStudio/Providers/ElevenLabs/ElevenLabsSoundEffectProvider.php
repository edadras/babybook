<?php

namespace App\Services\StoryStudio\Providers\ElevenLabs;

use App\Services\StoryStudio\Contracts\SoundEffectProviderInterface;
use App\Services\StoryStudio\Data\AudioResult;
use App\Services\StoryStudio\Support\ProviderHttp;

class ElevenLabsSoundEffectProvider implements SoundEffectProviderInterface
{
    public function __construct(private readonly ElevenLabsClient $client) {}

    public function generate(string $prompt, ?float $durationSeconds = null): AudioResult
    {
        $startedAt = microtime(true);
        $payload = ['text' => $prompt, 'prompt_influence' => 0.4, 'model_id' => $this->model()];

        if ($durationSeconds !== null) {
            $payload['duration_seconds'] = max(0.5, min(30, $durationSeconds));
        }

        $response = $this->client->request()
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->post('/sound-generation?output_format='.$this->client->outputFormat(), $payload);

        ProviderHttp::check('elevenlabs', 'sound-generation', $response, $startedAt);

        return new AudioResult($response->body(), $this->model(), 'audio/mpeg', 0, $response->header('request-id') ?: null);
    }

    public function name(): string
    {
        return 'elevenlabs';
    }

    public function model(): string
    {
        return (string) config('story-studio.elevenlabs.sfx_model', 'eleven_text_to_sound_v2');
    }
}
