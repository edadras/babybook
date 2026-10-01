<?php

namespace App\Services\StoryStudio\Data;

final readonly class VoicePreview
{
    public function __construct(
        public string $generatedVoiceId,
        public string $audioBytes,
        public string $mimeType = 'audio/mpeg',
        public ?int $durationMs = null,
    ) {}
}
