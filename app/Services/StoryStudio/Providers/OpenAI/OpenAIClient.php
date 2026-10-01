<?php

namespace App\Services\StoryStudio\Providers\OpenAI;

use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\ProviderHttp;
use Illuminate\Http\Client\PendingRequest;

class OpenAIClient
{
    public function request(?int $timeout = null): PendingRequest
    {
        $key = config('services.openai.key');

        if (! $key) {
            throw ProviderException::notConfigured('OpenAI', 'OPENAI_API_KEY');
        }

        $headers = ['Authorization' => 'Bearer '.$key];
        if ($organization = config('services.openai.organization')) {
            $headers['OpenAI-Organization'] = $organization;
        }

        ProviderHttp::throttle('openai');

        return ProviderHttp::client(
            'openai',
            config('services.openai.base_url', 'https://api.openai.com/v1'),
            $headers,
            $timeout ?? (int) config('story-studio.openai.timeout', 180),
        );
    }
}
