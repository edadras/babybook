<?php

namespace App\Services\StoryStudio\Data;

final readonly class ImageResult
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
        public int $height,
        public string $model,
        public ?string $requestId = null,
        public ?string $revisedPrompt = null,
    ) {}

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
