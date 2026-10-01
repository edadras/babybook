<?php

namespace App\Enums\StoryStudio;

enum SoundKind: string
{
    use HasOptions;

    case Effect = 'sfx';
    case Ambience = 'ambience';

    public function label(): string
    {
        return match ($this) {
            self::Effect => 'افکت',
            self::Ambience => 'صدای محیط',
        };
    }
}
