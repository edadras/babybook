<?php

namespace App\Enums\StoryStudio;

enum GenerationMode: string
{
    use HasOptions;

    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'خودکار',
            self::Manual => 'استودیو دستی (تأیید در هر مرحله)',
        };
    }
}
