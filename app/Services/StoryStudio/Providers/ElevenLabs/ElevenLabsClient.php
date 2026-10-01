<?php

namespace App\Services\StoryStudio\Providers\ElevenLabs;

use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;

class ElevenLabsClient
{
    public function request(?int $timeout = null): PendingRequest
    {
        $key = config('services.elevenlabs.key');

        if (! $key) {
            throw ProviderException::notConfigured('ElevenLabs', 'ELEVENLABS_API_KEY');
        }

        ProviderHttp::throttle('elevenlabs');

        return ProviderHttp::client(
            'elevenlabs',
            config('services.elevenlabs.base_url', 'https://api.elevenlabs.io/v1'),
            ['xi-api-key' => $key, 'Accept' => 'application/json'],
            $timeout ?? (int) config('story-studio.elevenlabs.timeout', 120),
        );
    }

    public function outputFormat(): string
    {
        return (string) config('story-studio.elevenlabs.output_format', 'mp3_44100_128');
    }
}
