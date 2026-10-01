<?php

namespace App\Enums\StoryStudio;

enum TargetAge: string
{
    use HasOptions;

    case Preschool = '3-5';
    case EarlyReader = '6-8';
    case Middle = '9-12';

    public function label(): string
    {
        return match ($this) {
            self::Preschool => '۳ تا ۵ سال',
            self::EarlyReader => '۶ تا ۸ سال',
            self::Middle => '۹ تا ۱۲ سال',
        };
    }
}
