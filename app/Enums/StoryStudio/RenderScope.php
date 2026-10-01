<?php

namespace App\Enums\StoryStudio;

enum RenderScope: string
{
    use HasOptions;

    case Scene = 'scene';
    case Preview = 'preview';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Scene => 'صحنه',
            self::Preview => 'پیش‌نمایش',
            self::Final => 'نهایی',
        };
    }
}
