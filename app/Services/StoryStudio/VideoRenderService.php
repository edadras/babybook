<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\SubtitleMode;
use App\Enums\StoryStudio\Transition;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\Exceptions\MediaProcessingException;
use App\Services\StoryStudio\Schema\StorySchemas;
use App\Services\StoryStudio\Support\Ffmpeg;
use ZipArchive;

/**
 * FFmpeg rendering: per-scene clips with subtle storybook motion, then the
 * final MP4 with soft transitions, subtitles, MP3, SRT/VTT, cover and ZIP.
 */
class VideoRenderService
{
    public const FPS = 25;

    public function __construct(
        private readonly Ffmpeg $ffmpeg,
        private readonly AssetStorageService $storage,
        private readonly TimelineService $timeline,
        private readonly SubtitleService $subtitles,
        private readonly CoverService $covers,
    ) {}

    public function resolveMotion(StoryScene $scene): ImageMotion
    {
        if ($scene->motion !== ImageMotion::Auto) {
            return $scene->motion;
        }

        $recommended = ImageMotion::tryFrom((string) data_get($scene->visual, 'recommended_motion'));
        if ($recommended && $recommended !== ImageMotion::Auto) {
            return $recommended;
        }

        $cycle = [ImageMotion::ZoomIn, ImageMotion::PanLeft, ImageMotion::ZoomOut, ImageMotion::PanRight];

        return $cycle[$scene->scene_number % count($cycle)];
    }

    /**
     * zoompan expressions; movement stays within ~8% so it remains subtle.
     *
     * @return array{z: string, x: string, y: string}
     */
    public function motionExpressions(ImageMotion $motion, int $frames): array
    {
        $d = max(1, $frames);
        $center = ['x' => 'iw/2-(iw/zoom/2)', 'y' => 'ih/2-(ih/zoom/2)'];

        return match ($motion) {
            ImageMotion::ZoomIn => ['z' => "1+0.08*on/{$d}"] + $center,
            ImageMotion::ZoomOut => ['z' => "1.08-0.08*on/{$d}"] + $center,
            ImageMotion::PanLeft => ['z' => '1.08', 'x' => "(iw-iw/zoom)*(1-on/{$d})", 'y' => $center['y']],
            ImageMotion::PanRight => ['z' => '1.08', 'x' => "(iw-iw/zoom)*on/{$d}", 'y' => $center['y']],
            ImageMotion::KenBurns => ['z' => "1+0.1*on/{$d}", 'x' => "(iw-iw/zoom)*(0.2+0.6*on/{$d})", 'y' => "(ih-ih/zoom)*(0.3+0.4*on/{$d})"],
            default => ['z' => '1', 'x' => '0', 'y' => '0'],
        };
    }

    public function renderScene(StoryScene $scene): StoryAsset
    {
        $project = $scene->project;
        $image = $scene->selectedImage() ?? throw new MediaProcessingException("Scene {$scene->scene_number} has no selected image.");
        $mix = $scene->selectedAudio(AudioAssetType::SceneMix) ?? throw new MediaProcessingException("Scene {$scene->scene_number} has no audio mix.");

        [$width, $height] = $project->frameSize();
        $durationMs = max(1000, (int) $mix->duration_ms);
        $frames = (int) ceil($durationMs / 1000 * self::FPS);
        $motion = $this->resolveMotion($scene);
        $expr = $this->motionExpressions($motion, $frames);
        $upW = $width * 2;
        $upH = $height * 2;

        $imagePath = $this->storage->localPath($image);
        $mixPath = $this->storage->localPath($mix);
        $output = $this->storage->tempPath('mp4');

        try {
            $filter = "[0:v]scale={$upW}:{$upH}:force_original_aspect_ratio=increase,crop={$upW}:{$upH},"
                ."zoompan=z='{$expr['z']}':x='{$expr['x']}':y='{$expr['y']}':d={$frames}:s={$width}x{$height}:fps=".self::FPS
                .',format=yuv420p,setsar=1[v]';

            $this->ffmpeg->run([
                '-i', $imagePath, '-i', $mixPath,
                '-filter_complex', $filter,
                '-map', '[v]', '-map', '1:a',
                '-t', Ffmpeg::seconds($durationMs),
                '-r', (string) self::FPS,
                '-c:v', 'libx264', '-preset', (string) config('story-studio.ffmpeg.preset', 'veryfast'), '-crf', '20',
                '-c:a', 'aac', '-b:a', '192k', '-ar', '44100',
                '-movflags', '+faststart', $output,
            ]);

            /** @var StoryAsset $asset */
            $asset = $this->storage->storeLocalFile(
                StoryAsset::class, $project, AssetType::SceneVideo->value, $scene->slot('scene_video'),
                'video', sprintf('scene_%02d_video', $scene->scene_number), $output, 'mp4',
                [
                    'scene_id' => $scene->getKey(), 'provider' => 'ffmpeg', 'model' => 'zoompan',
                    'width' => $width, 'height' => $height, 'duration_ms' => $this->ffmpeg->durationMs($output),
                    'metadata' => ['motion' => $motion->value, 'image_asset_id' => $image->getKey(), 'mix_asset_id' => $mix->getKey()],
                ],
            );

            return $asset;
        } finally {
            @unlink($output);
            if (! $this->storage->isLocalDisk($image)) {
                @unlink($imagePath);
            }
            if (! $this->storage->isLocalDisk($mix)) {
                @unlink($mixPath);
            }
        }
    }

    private function xfadeName(Transition $transition): string
    {
        return match ($transition) {
            Transition::Fade => 'fadeblack',
            Transition::Dissolve => 'dissolve',
            Transition::Slide => 'slideleft',
            default => 'fade',
        };
    }

    /**
     * Assemble the final outputs. Returns asset ids keyed by output name.
     *
     * @return array<string, string>
     */
    public function renderFinal(StoryProject $project): array
    {
        $project->loadMissing('settings');
        $scenes = $project->scenes()->with(['dialogues.character', 'soundEffects', 'project.settings'])->get();
        if ($scenes->isEmpty()) {
            throw new MediaProcessingException('No scenes to render.');
        }

        $clips = [];
        $durations = [];
        $transitionMs = [];
        $plans = [];
        foreach ($scenes as $scene) {
            $clip = $scene->assets()->where('type', AssetType::SceneVideo)->selected()->latest('version')->first()
                ?? throw new MediaProcessingException("Scene {$scene->scene_number} has not been rendered.");
            $clips[] = $clip;
            $durations[] = (int) $clip->duration_ms;
            $transitionMs[] = $this->timeline->transitionMs($scene);
            $plan = $this->timeline->planScene($scene);
            // Prefer the exact plan used for the mix (actual audio durations).
            $mix = $scene->selectedAudio(AudioAssetType::SceneMix);
            $plans[] = $mix?->metadata['plan'] ?? $plan;
        }

        $layout = $this->timeline->layout($durations, $transitionMs);
        [$width, $height] = $project->frameSize();
        $work = $this->storage->workDir('final');
        $results = [];
        $localClips = [];

        try {
            $args = [];
            foreach ($clips as $clip) {
                $path = $this->storage->localPath($clip);
                $localClips[] = [$clip, $path];
                array_push($args, '-i', $path);
            }

            // Subtitles
            $settings = $project->settings;
            $mode = $settings?->subtitle_mode ?? SubtitleMode::Persian;
            $cueScenes = [];
            foreach ($plans as $index => $plan) {
                $cueScenes[] = ['start_ms' => $layout['starts'][$index], 'plan' => $plan];
            }
            $cues = $this->subtitles->cues($cueScenes);

            $filters = [];
            $vLabel = '[0:v]';
            $aLabel = '[0:a]';
            $length = $durations[0];
            for ($i = 1; $i < count($clips); $i++) {
                $overlap = $layout['transitions'][$i - 1];
                $offset = Ffmpeg::seconds($length - $overlap);
                $d = Ffmpeg::seconds(max(40, $overlap));
                $name = $this->xfadeName($scenes[$i - 1]->transition);
                $filters[] = "{$vLabel}[{$i}:v]xfade=transition={$name}:duration={$d}:offset={$offset}[v{$i}]";
                $filters[] = "{$aLabel}[{$i}:a]acrossfade=d={$d}:c1=tri:c2=tri[a{$i}]";
                $vLabel = "[v{$i}]";
                $aLabel = "[a{$i}]";
                $length = $length + $durations[$i] - $overlap;
            }

            $burn = $settings?->burn_subtitles && $mode !== SubtitleMode::None && $cues !== [];
            if ($burn) {
                file_put_contents($work.'/burn.ass', $this->subtitles->ass($cues, $mode, $width, $height, (int) ($settings->subtitle_font_size ?? 42), config('story-studio.ffmpeg.subtitle_font', 'Vazirmatn')));
                $filters[] = "{$vLabel}subtitles=f=".Ffmpeg::filterPath($work.'/burn.ass').':fontsdir='.Ffmpeg::filterPath($this->covers->fontsDir()).'[vout]';
                $vLabel = '[vout]';
            }
            if ($filters === []) {
                $filters[] = '[0:v]null[vout]';
                $vLabel = '[vout]';
            }

            $mp4 = $work.'/story.mp4';
            $this->ffmpeg->run([
                ...$args,
                '-filter_complex', implode(';', $filters),
                '-map', $vLabel, '-map', $aLabel,
                '-c:v', 'libx264', '-preset', (string) config('story-studio.ffmpeg.final_preset', 'medium'), '-crf', '20', '-pix_fmt', 'yuv420p',
                '-c:a', 'aac', '-b:a', '192k',
                '-movflags', '+faststart', $mp4,
            ]);

            $mp3 = $work.'/story.mp3';
            $this->ffmpeg->run(['-i', $mp4, '-vn', '-c:a', 'libmp3lame', '-b:a', '192k', '-id3v2_version', '3', '-metadata', 'title='.$project->title, $mp3]);

            $finalDuration = $this->ffmpeg->durationMs($mp4);
            $slot = fn (string $name) => 'project:'.$project->getKey().':'.$name;
            $meta = ['scenes' => count($clips), 'subtitle_mode' => $mode->value, 'burned_subtitles' => $burn];

            $results['video'] = $this->storage->storeLocalFile(StoryAsset::class, $project, AssetType::FinalVideo->value, $slot('final_video'), 'output', 'story', $mp4, 'mp4',
                ['provider' => 'ffmpeg', 'model' => 'final', 'width' => $width, 'height' => $height, 'duration_ms' => $finalDuration, 'metadata' => $meta])->getKey();
            $results['audio'] = $this->storage->storeLocalFile(StoryAsset::class, $project, AssetType::FinalAudio->value, $slot('final_audio'), 'output', 'story', $mp3, 'mp3',
                ['provider' => 'ffmpeg', 'model' => 'final', 'duration_ms' => $this->ffmpeg->durationMs($mp3)])->getKey();

            $srtMode = $mode === SubtitleMode::None ? SubtitleMode::Persian : $mode;
            file_put_contents($work.'/story.srt', $this->subtitles->srt($cues, $srtMode));
            file_put_contents($work.'/story.vtt', $this->subtitles->vtt($cues, $srtMode));
            $results['srt'] = $this->storage->storeLocalFile(StoryAsset::class, $project, AssetType::SubtitleSrt->value, $slot('srt'), 'output', 'story', $work.'/story.srt', 'srt',
                ['provider' => 'studio', 'metadata' => ['cues' => count($cues)]])->getKey();
            $results['vtt'] = $this->storage->storeLocalFile(StoryAsset::class, $project, AssetType::SubtitleVtt->value, $slot('vtt'), 'output', 'story', $work.'/story.vtt', 'vtt',
                ['provider' => 'studio', 'metadata' => ['cues' => count($cues)]])->getKey();

            if ($cover = $this->covers->compose($project)) {
                $results['cover'] = $cover->getKey();
            }

            $results['zip'] = $this->buildZip($project, $work)->getKey();

            $project->forceFill(['duration_ms' => $finalDuration])->save();

            return $results;
        } finally {
            foreach ($localClips as [$clip, $path]) {
                if (! $this->storage->isLocalDisk($clip)) {
                    @unlink($path);
                }
            }
            AssetStorageService::deleteDirectory($work);
        }
    }

    /**
     * ZIP with the final outputs plus every selected individual asset.
     */
    public function buildZip(StoryProject $project, string $work): StoryAsset
    {
        $zipPath = $work.'/assets.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new MediaProcessingException('Cannot create ZIP archive.');
        }

        $temps = [];
        $add = function (StoryAsset|StoryAudioAsset $asset, string $name) use ($zip, &$temps) {
            $path = $this->storage->localPath($asset);
            if (! $this->storage->isLocalDisk($asset)) {
                $temps[] = $path;
            }
            if (is_file($path)) {
                $zip->addFile($path, $name);
            }
        };

        $manifest = ['title' => $project->title, 'generated_at' => now()->toIso8601String(), 'scenes' => []];

        foreach ([AssetType::FinalVideo, AssetType::FinalAudio, AssetType::SubtitleSrt, AssetType::SubtitleVtt, AssetType::Cover] as $type) {
            if ($asset = $project->latestAsset($type)) {
                $add($asset, 'story.'.pathinfo($asset->file_path, PATHINFO_EXTENSION));
            }
        }

        foreach ($project->characters()->where('is_narrator', false)->get() as $character) {
            foreach ($character->referenceAssets()->selected()->get() as $reference) {
                $add($reference, sprintf('characters/%s_%s_v%d.%s', $character->key, $reference->type->value, $reference->version, pathinfo($reference->file_path, PATHINFO_EXTENSION)));
            }
        }

        foreach ($project->scenes as $scene) {
            $n = sprintf('scene_%02d', $scene->scene_number);
            if ($image = $scene->selectedImage()) {
                $add($image, "scenes/{$n}_image_v{$image->version}.".pathinfo($image->file_path, PATHINFO_EXTENSION));
            }
            foreach ($scene->audioAssets()->selected()->with(['character', 'soundEffect'])->get() as $audio) {
                $label = match ($audio->type) {
                    AudioAssetType::Narration => 'narrator',
                    AudioAssetType::Dialogue => ($audio->character?->key ?? 'character').'_'.substr((string) $audio->dialogue_id, -6),
                    AudioAssetType::SoundEffect, AudioAssetType::Ambience => $audio->type->value.'_'.substr((string) $audio->sound_effect_id, -6),
                    default => $audio->type->value,
                };
                $add($audio, "scenes/{$n}_{$label}_v{$audio->version}.mp3");
            }
            $manifest['scenes'][] = ['number' => $scene->scene_number, 'title' => $scene->title, 'narration' => $scene->narration_original];
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        try {
            /** @var StoryAsset $asset */
            $asset = $this->storage->storeLocalFile(StoryAsset::class, $project, AssetType::AssetsZip->value, 'project:'.$project->getKey().':zip', 'output', 'story_assets', $zipPath, 'zip', ['provider' => 'studio']);

            return $asset;
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function motions(): array
    {
        return StorySchemas::MOTIONS;
    }
}
