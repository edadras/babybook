<?php

namespace App\Enums\StoryStudio;

enum ImageMotion: string
{
    use HasOptions;

    case None = 'none';
    case ZoomIn = 'zoom_in';
    case ZoomOut = 'zoom_out';
    case PanLeft = 'pan_left';
    case PanRight = 'pan_right';
    case KenBurns = 'ken_burns';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون حرکت',
            self::ZoomIn => 'زوم آرام به داخل',
            self::ZoomOut => 'زوم آرام به بیرون',
            self::PanLeft => 'حرکت به چپ',
            self::PanRight => 'حرکت به راست',
            self::KenBurns => 'کن برنز',
            self::Auto => 'خودکار (پیشنهاد کارگردان)',
        };
    }
}
