<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\Support\Ffmpeg;

/**
 * Final cover: AI artwork generated WITHOUT text, with the Persian title
 * overlaid deterministically through libass (proper RTL shaping) and Vazirmatn.
 */
class CoverService
{
    public function __construct(
        private readonly Ffmpeg $ffmpeg,
        private readonly AssetStorageService $storage,
    ) {}

    public function compose(StoryProject $project): ?StoryAsset
    {
        $art = $project->latestAsset(AssetType::CoverArt)
            ?? $project->scenes()->first()?->selectedImage();

        if (! $art) {
            return null;
        }

        [$width, $height] = $project->frameSize();
        $work = $this->storage->workDir('cover');
        $output = $work.'/cover.jpg';
        $artPath = $this->storage->localPath($art);

        try {
            $filter = "scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height}";

            if ($project->settings?->show_title_on_cover ?? true) {
                file_put_contents($work.'/title.ass', $this->titleAss($project->title, $width, $height));
                $filter .= ',subtitles=f='.Ffmpeg::filterPath($work.'/title.ass').':fontsdir='.Ffmpeg::filterPath($this->fontsDir());
            }

            $this->ffmpeg->run(['-i', $artPath, '-vf', $filter, '-frames:v', '1', '-q:v', '2', $output], 120);

            /** @var StoryAsset $cover */
            $cover = $this->storage->storeLocalFile(
                StoryAsset::class, $project, AssetType::Cover->value, 'project:'.$project->getKey().':cover',
                'output', 'cover', $output, 'jpg',
                ['provider' => 'ffmpeg', 'model' => 'libass', 'width' => $width, 'height' => $height, 'metadata' => ['art_asset_id' => $art->getKey()]],
            );
            $project->forceFill(['cover_asset_id' => $cover->getKey()])->save();

            return $cover;
        } finally {
            if (! $this->storage->isLocalDisk($art)) {
                @unlink($artPath);
            }
            AssetStorageService::deleteDirectory($work);
        }
    }

    public function fontsDir(): string
    {
        return (string) config('story-studio.ffmpeg.fonts_dir', resource_path('fonts'));
    }

    private function titleAss(string $title, int $width, int $height): string
    {
        $size = (int) round(min($width, $height) * 0.085);
        $margin = (int) round($height * 0.07);
        $title = str_replace(['{', '}', "\n"], ['(', ')', ' '], $title);
        $font = config('story-studio.ffmpeg.subtitle_font', 'Vazirmatn');

        return "[Script Info]\nScriptType: v4.00+\nPlayResX: {$width}\nPlayResY: {$height}\nWrapStyle: 0\n\n"
            ."[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n"
            ."Style: Title,{$font},{$size},&H00FFFFFF,&H00FFFFFF,&H00402A1E,&H64000000,-1,0,0,0,100,100,0,0,1,".max(3, (int) ($size / 9)).',3,8,'.(int) ($width * 0.08).','.(int) ($width * 0.08).",{$margin},1\n\n"
            ."[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n"
            ."Dialogue: 0,0:00:00.00,0:00:10.00,Title,,0,0,0,,{$title}\n";
    }
}
