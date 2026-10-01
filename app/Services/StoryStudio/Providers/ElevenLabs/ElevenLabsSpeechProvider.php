<?php

namespace App\Services\StoryStudio\Providers\ElevenLabs;

use App\Services\StoryStudio\Contracts\SpeechProviderInterface;
use App\Services\StoryStudio\Data\AudioResult;
use App\Services\StoryStudio\Support\ProviderHttp;
use Illuminate\Support\Str;

class ElevenLabsSpeechProvider implements SpeechProviderInterface
{
    public function __construct(private readonly ElevenLabsClient $client) {}

    public function synthesize(string $text, string $voiceId, array $voiceSettings = []): AudioResult
    {
        $startedAt = microtime(true);
        $payload = ['text' => $text, 'model_id' => $this->model()];

        if ($voiceSettings !== []) {
            $payload['voice_settings'] = $voiceSettings;
        }
        if ($language = config('story-studio.elevenlabs.language_code')) {
            $payload['language_code'] = $language;
        }

        $response = $this->client->request()
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->post('/text-to-speech/'.rawurlencode($voiceId).'?output_format='.$this->client->outputFormat(), $payload);

        ProviderHttp::check('elevenlabs', 'text-to-speech', $response, $startedAt);

        return new AudioResult(
            $response->body(),
            $this->model(),
            'audio/mpeg',
            (int) ($response->header('x-character-count') ?: mb_strlen($text)),
            $response->header('request-id') ?: null,
        );
    }

    public function supportsAudioTags(): bool
    {
        return Str::startsWith($this->model(), 'eleven_v3');
    }

    public function listVoices(): array
    {
        $startedAt = microtime(true);
        $response = $this->client->request(30)->get('/voices');
        ProviderHttp::check('elevenlabs', 'voices', $response, $startedAt);

        return collect($response->json('voices', []))
            ->map(fn (array $voice) => [
                'voice_id' => (string) $voice['voice_id'],
                'name' => (string) ($voice['name'] ?? $voice['voice_id']),
                'description' => $voice['description'] ?? (isset($voice['labels']) ? implode('، ', array_filter((array) $voice['labels'])) : null),
                'preview_url' => $voice['preview_url'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function name(): string
    {
        return 'elevenlabs';
    }

    public function model(): string
    {
        return (string) config('story-studio.elevenlabs.tts_model');
    }
}
