<?php

namespace App\Enums\StoryStudio;

enum Resolution: string
{
    use HasOptions;

    case HD = '720p';
    case FullHD = '1080p';

    public function label(): string
    {
        return $this->value;
    }

    public function shortEdge(): int
    {
        return match ($this) {
            self::HD => 720,
            self::FullHD => 1080,
        };
    }
}
