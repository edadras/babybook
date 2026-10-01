<?php

namespace App\Enums\StoryStudio;

enum SubtitleMode: string
{
    use HasOptions;

    case None = 'none';
    case Persian = 'fa';
    case English = 'en';
    case Bilingual = 'bilingual';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون زیرنویس',
            self::Persian => 'فارسی',
            self::English => 'انگلیسی',
            self::Bilingual => 'دوزبانه',
        };
    }
}
