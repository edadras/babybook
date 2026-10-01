<?php

namespace App\Enums\StoryStudio;

enum ProjectStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Analyzing = 'analyzing';
    case CharactersReady = 'characters_ready';
    case StoryboardReady = 'storyboard_ready';
    case GeneratingImages = 'generating_images';
    case GeneratingVoices = 'generating_voices';
    case GeneratingAudio = 'generating_audio';
    case ReadyForReview = 'ready_for_review';
    case Rendering = 'rendering';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Analyzing => 'در حال تحلیل',
            self::CharactersReady => 'شخصیت‌ها آماده',
            self::StoryboardReady => 'استوری‌بورد آماده',
            self::GeneratingImages => 'ساخت تصاویر',
            self::GeneratingVoices => 'ساخت صداها',
            self::GeneratingAudio => 'ساخت صوت',
            self::ReadyForReview => 'آماده بازبینی',
            self::Rendering => 'در حال رندر',
            self::Completed => 'تکمیل شده',
            self::Failed => 'ناموفق',
        };
    }

    /**
     * Visual tone used by the UI status pill.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::ReadyForReview, self::CharactersReady, self::StoryboardReady => 'info',
            default => 'working',
        };
    }

    /**
     * True while background work is expected to be running for the project.
     */
    public function isWorking(): bool
    {
        return $this->tone() === 'working';
    }
}
