<?php

namespace App\Enums\StoryStudio;

enum Transition: string
{
    use HasOptions;

    case Crossfade = 'crossfade';
    case Fade = 'fade';
    case Dissolve = 'dissolve';
    case Slide = 'slide';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Crossfade => 'کراس‌فید نرم',
            self::Fade => 'محو شدن',
            self::Dissolve => 'حل شدن',
            self::Slide => 'اسلاید',
            self::None => 'بدون ترنزیشن',
        };
    }
}
