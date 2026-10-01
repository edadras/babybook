<?php

namespace App\Enums\StoryStudio;

enum StoryCategory: string
{
    use HasOptions;

    case Bedtime = 'bedtime';
    case Adventure = 'adventure';
    case Friendship = 'friendship';
    case Fable = 'fable';
    case Fantasy = 'fantasy';
    case Nature = 'nature';
    case Educational = 'educational';
    case Folk = 'folk';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bedtime => 'قصه شب',
            self::Adventure => 'ماجراجویی',
            self::Friendship => 'دوستی',
            self::Fable => 'حکایت و فابل',
            self::Fantasy => 'فانتزی',
            self::Nature => 'طبیعت و حیوانات',
            self::Educational => 'آموزشی',
            self::Folk => 'قصه‌های عامیانه',
            self::Other => 'سایر',
        };
    }
}
