<?php

namespace App\Enums\StoryStudio;

/**
 * Lifecycle of a generated or derived item (asset, scene, dialogue...).
 */
enum AssetStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Queued = 'queued';
    case Generating = 'generating';
    case Ready = 'ready';
    case Approved = 'approved';
    case Stale = 'stale';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Queued => 'در صف',
            self::Generating => 'در حال ساخت',
            self::Ready => 'آماده',
            self::Approved => 'تأیید شده',
            self::Stale => 'نیاز به بازسازی',
            self::Failed => 'ناموفق',
        };
    }
}
