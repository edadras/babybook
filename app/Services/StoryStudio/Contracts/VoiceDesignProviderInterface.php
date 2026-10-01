<?php

namespace App\Services\StoryStudio\Contracts;

use App\Services\StoryStudio\Data\VoicePreview;

interface VoiceDesignProviderInterface
{
    /**
     * Generate candidate voices from a description.
     *
     * @return list<VoicePreview>
     */
    public function design(string $description, string $sampleText): array;

    /**
     * Persist a chosen preview as a permanent voice and return its voice id.
     */
    public function createVoice(string $generatedVoiceId, string $name, string $description): string;

    public function name(): string;

    public function model(): string;
}
