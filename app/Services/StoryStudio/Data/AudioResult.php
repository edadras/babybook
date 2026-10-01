<?php

namespace App\Services\StoryStudio\Data;

final readonly class AudioResult
{
    public function __construct(
        public string $bytes,
        public string $model,
        public string $mimeType = 'audio/mpeg',
        public int $characters = 0,
        public ?string $requestId = null,
    ) {}

    public function extension(): string
    {
        return match ($this->mimeType) {
            'audio/wav', 'audio/x-wav' => 'wav',
            default => 'mp3',
        };
    }
}
