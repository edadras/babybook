<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AudioAssetType;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\Support\Ffmpeg;

/**
 * Mixes one scene: voices on top, effects/ambience under them, and music that
 * is automatically ducked (sidechain compression) whenever anyone speaks.
 */
class AudioMixService
{
    public function __construct(
        private readonly Ffmpeg $ffmpeg,
        private readonly AssetStorageService $storage,
        private readonly TimelineService $timeline,
    ) {}

    public function mixScene(StoryScene $scene): StoryAudioAsset
    {
        $plan = $this->timeline->planScene($scene);
        $duration = $plan['duration_ms'];
        $seconds = Ffmpeg::seconds($duration);
        $settings = $scene->project->settings;

        $args = [];
        $filters = [];
        $inputIndex = 0;
        $temps = [];

        $addInput = function (StoryAudioAsset $asset, bool $loop) use (&$args, &$inputIndex, &$temps): int {
            $path = $this->storage->localPath($asset);
            if (! $this->storage->isLocalDisk($asset)) {
                $temps[] = $path;
            }
            if ($loop) {
                array_push($args, '-stream_loop', '-1');
            }
            array_push($args, '-i', $path);

            return $inputIndex++;
        };
        $fmt = 'aformat=sample_rates=44100:channel_layouts=stereo';

        $voiceLabels = [];
        foreach ($plan['voice'] as $n => $item) {
            $asset = $item['audio_id'] ? StoryAudioAsset::find($item['audio_id']) : null;
            if (! $asset) {
                continue;
            }
            $i = $addInput($asset, false);
            $delay = max(0, $item['start_ms']);
            $filters[] = "[{$i}:a]{$fmt},volume={$item['volume']},adelay={$delay}|{$delay}[v{$n}]";
            $voiceLabels[] = "[v{$n}]";
        }

        $filters[] = $voiceLabels
            ? implode('', $voiceLabels).'amix=inputs='.count($voiceLabels).":normalize=0:dropout_transition=0,apad,atrim=0:{$seconds}[voice]"
            : "anullsrc=r=44100:cl=stereo,atrim=0:{$seconds}[voice]";

        $bed = [];
        foreach ($plan['effects'] as $n => $item) {
            $asset = $item['enabled'] && $item['audio_id'] ? StoryAudioAsset::find($item['audio_id']) : null;
            if (! $asset) {
                continue;
            }
            $i = $addInput($asset, $item['loop']);
            $len = Ffmpeg::seconds($item['duration_ms']);
            $fadeOut = Ffmpeg::seconds(max(0, $item['duration_ms'] - 800));
            $delay = $item['start_ms'];
            $filters[] = "[{$i}:a]{$fmt},atrim=0:{$len},volume={$item['volume']},afade=t=in:d=0.3,afade=t=out:st={$fadeOut}:d=0.8,adelay={$delay}|{$delay}[e{$n}]";
            $bed[] = "[e{$n}]";
        }

        $music = $plan['music'] ? StoryAudioAsset::find($plan['music']['audio_id']) : null;
        if ($music) {
            $i = $addInput($music, true);
            $fadeIn = Ffmpeg::seconds(max(300, $plan['music']['fade_in_ms']));
            $fadeOutMs = max(600, $plan['music']['fade_out_ms']);
            $fadeOutStart = Ffmpeg::seconds(max(0, $duration - $fadeOutMs));
            $ratio = number_format(1 + ((int) ($settings?->ducking_db ?? 12)) / 2.5, 2, '.', '');
            $filters[] = '[voice]asplit=2[voiceout][sidechain]';
            $filters[] = "[{$i}:a]{$fmt},atrim=0:{$seconds},volume={$plan['music']['volume']},afade=t=in:d={$fadeIn},afade=t=out:st={$fadeOutStart}:d=".Ffmpeg::seconds($fadeOutMs).'[musicraw]';
            $filters[] = "[musicraw][sidechain]sidechaincompress=threshold=0.015:ratio={$ratio}:attack=20:release=450:makeup=1[music]";
            $bed[] = '[music]';
            $voiceOut = '[voiceout]';
        } else {
            $voiceOut = '[voice]';
        }

        if ($bed) {
            $filters[] = implode('', $bed).'amix=inputs='.count($bed).':normalize=0:dropout_transition=0[bed]';
            $filters[] = "{$voiceOut}[bed]amix=inputs=2:normalize=0:duration=first[mix]";
        } else {
            $filters[] = "{$voiceOut}anull[mix]";
        }
        $filters[] = "[mix]atrim=0:{$seconds},loudnorm=I=-16:TP=-1.5:LRA=11,aresample=44100[out]";

        $output = $this->storage->tempPath('mp3');

        try {
            $this->ffmpeg->run([...$args, '-filter_complex', implode(';', $filters), '-map', '[out]', '-ac', '2', '-c:a', 'libmp3lame', '-b:a', '192k', $output]);
            $actual = $this->ffmpeg->durationMs($output);

            /** @var StoryAudioAsset $asset */
            $asset = $this->storage->storeLocalFile(
                StoryAudioAsset::class, $scene->project, AudioAssetType::SceneMix->value, $scene->slot('scene_mix'),
                'audio', sprintf('scene_%02d_mix', $scene->scene_number), $output, 'mp3',
                [
                    'scene_id' => $scene->getKey(),
                    'provider' => 'ffmpeg',
                    'model' => 'mix',
                    'duration_ms' => $actual,
                    'metadata' => ['plan' => $plan, 'planned_duration_ms' => $duration],
                ],
            );

            return $asset;
        } finally {
            @unlink($output);
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }
}
