<?php

namespace App\Enums\StoryStudio;

enum CostCategory: string
{
    use HasOptions;

    case OpenAIText = 'openai_text';
    case OpenAIImage = 'openai_image';
    case ElevenLabsVoiceDesign = 'elevenlabs_voice_design';
    case ElevenLabsNarration = 'elevenlabs_narration';
    case ElevenLabsDialogue = 'elevenlabs_dialogue';
    case ElevenLabsSoundEffects = 'elevenlabs_sfx';
    case Rendering = 'rendering';

    public function label(): string
    {
        return match ($this) {
            self::OpenAIText => 'OpenAI — تحلیل و کارگردانی',
            self::OpenAIImage => 'OpenAI — تصویر',
            self::ElevenLabsVoiceDesign => 'ElevenLabs — طراحی صدا',
            self::ElevenLabsNarration => 'ElevenLabs — روایت',
            self::ElevenLabsDialogue => 'ElevenLabs — دیالوگ',
            self::ElevenLabsSoundEffects => 'ElevenLabs — افکت صوتی',
            self::Rendering => 'رندر',
        };
    }
}
