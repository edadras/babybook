<?php

namespace App\Enums\StoryStudio;

enum AudioAssetType: string
{
    use HasOptions;

    case Narration = 'narration';
    case Dialogue = 'dialogue';
    case SoundEffect = 'sfx';
    case Ambience = 'ambience';
    case Music = 'music';
    case SceneMix = 'scene_mix';
    case VoicePreview = 'voice_preview';

    public function label(): string
    {
        return match ($this) {
            self::Narration => 'روایت',
            self::Dialogue => 'دیالوگ',
            self::SoundEffect => 'افکت صوتی',
            self::Ambience => 'صدای محیط',
            self::Music => 'موسیقی',
            self::SceneMix => 'میکس صحنه',
            self::VoicePreview => 'نمونه صدا',
        };
    }
}
