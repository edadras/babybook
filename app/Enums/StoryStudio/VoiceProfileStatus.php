<?php

namespace App\Enums\StoryStudio;

enum VoiceProfileStatus: string
{
    use HasOptions;

    case Candidate = 'candidate';
    case Selected = 'selected';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'پیشنهادی',
            self::Selected => 'انتخاب شده',
            self::Rejected => 'رد شده',
        };
    }
}
