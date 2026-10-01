<?php

namespace App\Enums\StoryStudio;

/**
 * Ordered stages of the story production pipeline.
 */
enum PipelineStage: string
{
    use HasOptions;

    case Analyze = 'analyze';
    case Characters = 'characters';
    case CharacterReferences = 'character_references';
    case VoiceCasting = 'voice_casting';
    case Scenes = 'scenes';
    case Images = 'images';
    case Audio = 'audio';
    case SoundEffects = 'sfx';
    case Mix = 'mix';
    case Preview = 'preview';
    case FinalRender = 'final_render';

    public function label(): string
    {
        return match ($this) {
            self::Analyze => 'تحلیل داستان',
            self::Characters => 'شخصیت‌ها',
            self::CharacterReferences => 'تصاویر مرجع شخصیت',
            self::VoiceCasting => 'انتخاب صدا',
            self::Scenes => 'صحنه‌ها',
            self::Images => 'تصاویر',
            self::Audio => 'صدا',
            self::SoundEffects => 'افکت‌ها',
            self::Mix => 'میکس صوتی',
            self::Preview => 'پیش‌نمایش',
            self::FinalRender => 'رندر نهایی',
        };
    }

    /**
     * Relative share of total effort, used to compute overall progress.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Analyze, self::Characters, self::Scenes, self::Preview => 5,
            self::CharacterReferences, self::VoiceCasting, self::SoundEffects, self::Mix => 8,
            self::Images => 20,
            self::Audio => 15,
            self::FinalRender => 13,
        };
    }

    /**
     * Stages that wait for administrator approval in manual mode.
     */
    public function requiresApprovalInManualMode(): bool
    {
        return in_array($this, [
            self::Analyze,
            self::Characters,
            self::VoiceCasting,
            self::Scenes,
            self::Preview,
        ], true);
    }
}
