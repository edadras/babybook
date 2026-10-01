<?php

namespace App\Services\StoryStudio\Contracts;

use App\Services\StoryStudio\Data\AudioResult;

interface SpeechProviderInterface
{
    /**
     * @param  array<string, mixed>  $voiceSettings
     */
    public function synthesize(string $text, string $voiceId, array $voiceSettings = []): AudioResult;

    /**
     * Whether inline expressive tags such as "[whispers]" are understood.
     */
    public function supportsAudioTags(): bool;

    /**
     * @return list<array{voice_id: string, name: string, description: ?string, preview_url: ?string}>
     */
    public function listVoices(): array;

    public function name(): string;

    public function model(): string;
}
