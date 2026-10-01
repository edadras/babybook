<?php

namespace App\Enums\StoryStudio;

/**
 * Visual and packaged outputs stored in story_assets.
 */
enum AssetType: string
{
    use HasOptions;

    case SceneImage = 'scene_image';
    case CharacterFront = 'character_ref_front';
    case CharacterThreeQuarter = 'character_ref_three_quarter';
    case CharacterFullBody = 'character_ref_full_body';
    case LocationReference = 'location_ref';
    case CoverArt = 'cover_art';
    case Cover = 'cover';
    case SceneVideo = 'scene_video';
    case PreviewVideo = 'preview_video';
    case FinalVideo = 'final_video';
    case FinalAudio = 'final_audio';
    case SubtitleSrt = 'subtitle_srt';
    case SubtitleVtt = 'subtitle_vtt';
    case AssetsZip = 'assets_zip';

    public function label(): string
    {
        return match ($this) {
            self::SceneImage => 'تصویر صحنه',
            self::CharacterFront => 'نمای روبه‌رو',
            self::CharacterThreeQuarter => 'نمای سه‌رخ',
            self::CharacterFullBody => 'تمام‌قد',
            self::LocationReference => 'مرجع مکان',
            self::CoverArt => 'طرح جلد (بدون متن)',
            self::Cover => 'جلد',
            self::SceneVideo => 'ویدیوی صحنه',
            self::PreviewVideo => 'پیش‌نمایش',
            self::FinalVideo => 'ویدیوی نهایی',
            self::FinalAudio => 'کتاب صوتی',
            self::SubtitleSrt => 'زیرنویس SRT',
            self::SubtitleVtt => 'زیرنویس VTT',
            self::AssetsZip => 'بسته فایل‌ها',
        };
    }
}
