<?php

namespace App\Enums\StoryStudio;

enum NarrationStyle: string
{
    use HasOptions;

    case Bedtime = 'bedtime';
    case Warm = 'warm';
    case Playful = 'playful';
    case Dramatic = 'dramatic';
    case Educational = 'educational';

    public function label(): string
    {
        return match ($this) {
            self::Bedtime => 'آرام، قصه شب',
            self::Warm => 'گرم و صمیمی',
            self::Playful => 'شاد و بازیگوش',
            self::Dramatic => 'نمایشی و پرهیجان',
            self::Educational => 'آموزشی و شمرده',
        };
    }
}
