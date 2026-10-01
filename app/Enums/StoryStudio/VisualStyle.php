<?php

namespace App\Enums\StoryStudio;

enum VisualStyle: string
{
    use HasOptions;

    case ChildrensBook = 'childrens_book';
    case Watercolor = 'watercolor';
    case Soft3D = 'soft_3d';
    case Clay = 'clay';
    case PaperCut = 'paper_cut';
    case Cartoon = 'cartoon';
    case Fantasy = 'fantasy';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::ChildrensBook => 'تصویرسازی کتاب کودک',
            self::Watercolor => 'آبرنگ',
            self::Soft3D => 'انیمیشن سه‌بعدی نرم',
            self::Clay => 'خمیری (Clay)',
            self::PaperCut => 'کاغذ برش‌خورده',
            self::Cartoon => 'کارتونی',
            self::Fantasy => 'فانتزی',
            self::Custom => 'سفارشی',
        };
    }

    /**
     * Base English style fragment for the global Visual Style Bible.
     * Custom styles rely entirely on the project's custom instructions.
     */
    public function promptFragment(): ?string
    {
        return match ($this) {
            self::ChildrensBook => "Classic children's picture-book illustration, soft gouache texture, gentle outlines, warm friendly palette",
            self::Watercolor => 'Delicate watercolor illustration, soft paper texture, light washes, gentle color bleeding',
            self::Soft3D => 'Soft 3D animated film look, rounded friendly shapes, subsurface lighting, cozy materials',
            self::Clay => 'Handmade claymation look, plasticine textures, visible fingerprints, miniature set lighting',
            self::PaperCut => 'Layered paper-cut collage, visible paper grain, soft drop shadows between layers',
            self::Cartoon => 'Clean 2D cartoon style, bold simple shapes, flat colors with soft shading',
            self::Fantasy => 'Whimsical fantasy storybook painting, magical glow, rich but gentle colors',
            self::Custom => null,
        };
    }
}
