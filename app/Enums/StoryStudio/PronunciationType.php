<?php

namespace App\Enums\StoryStudio;

enum PronunciationType: string
{
    use HasOptions;

    case General = 'general';
    case CharacterName = 'character_name';
    case PersonName = 'person_name';
    case Place = 'place';
    case Phoneme = 'phoneme';

    public function label(): string
    {
        return match ($this) {
            self::General => 'عمومی',
            self::CharacterName => 'نام شخصیت',
            self::PersonName => 'نام ایرانی',
            self::Place => 'مکان',
            self::Phoneme => 'آوانگاری (IPA)',
        };
    }
}
