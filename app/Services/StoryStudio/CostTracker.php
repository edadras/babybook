<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\GenerationStatus;
use App\Models\StoryStudio\StoryProject;

/**
 * Prices generations from the configured price table and aggregates
 * estimated vs. actual cost per category.
 */
class CostTracker
{
    public function price(CostCategory $category, int $inputUnits, int $outputUnits = 0): float
    {
        $p = config('story-studio.pricing');

        return round(match ($category) {
            CostCategory::OpenAIText => $inputUnits / 1e6 * $p['openai_text']['input_per_million'] + $outputUnits / 1e6 * $p['openai_text']['output_per_million'],
            CostCategory::OpenAIImage => $inputUnits * $p['openai_image']['per_image'],
            CostCategory::ElevenLabsNarration, CostCategory::ElevenLabsDialogue => $inputUnits / 1000 * $p['elevenlabs']['tts_per_1k_chars'],
            CostCategory::ElevenLabsVoiceDesign => $inputUnits * $p['elevenlabs']['voice_design_per_call'],
            CostCategory::ElevenLabsSoundEffects => $inputUnits * $p['elevenlabs']['sfx_per_generation'],
            CostCategory::Rendering => 0.0,
        }, 6);
    }

    /**
     * Pre-flight estimate for generating the whole story.
     *
     * @return array{lines: array<string, array{label: string, units: int, unit: string, cost: float}>, total: float}
     */
    public function estimate(StoryProject $project): array
    {
        $chars = mb_strlen($project->original_text);
        $analysed = $project->scenes()->exists();
        $done = [AssetStatus::Ready, AssetStatus::Approved];

        if ($analysed) {
            // Only work that has not been produced yet (completed items are never regenerated).
            $scenes = $project->scenes()->with(['dialogues', 'soundEffects'])->get();
            $images = $scenes->filter(fn ($s) => ! in_array($s->workState('image'), $done, true))->count()
                + 3 * $project->characters()->where('is_major', true)->where('is_narrator', false)->get()->filter(fn ($c) => ! in_array($c->status, $done, true))->count()
                + (in_array($project->workState('cover_art'), $done, true) ? 0 : 1);
            $voiceDesigns = app(StoryPipelineService::class)->speakingCharacters($project)->whereNull('voice_profile_id')->count();
            $narrationChars = $scenes->filter(fn ($s) => ! in_array($s->workState('narration'), $done, true))->sum(fn ($s) => mb_strlen((string) $s->narration_original));
            $dialogues = $scenes->flatMap->dialogues->filter(fn ($d) => ! in_array($d->status, $done, true));
            $dialogueChars = $dialogues->sum(fn ($d) => mb_strlen($d->text_original));
            $sfx = $scenes->flatMap->soundEffects->filter(fn ($e) => $e->enabled && ! in_array($e->status, $done, true))->count();
        } else {
            $sceneCount = (int) max(3, min(config('story-studio.limits.max_scenes'), ceil($chars / 350)));
            $majorCharacters = max(2, min(6, (int) ceil($chars / 1500)));
            $images = $sceneCount + $majorCharacters * 3 + 1;
            $voiceDesigns = $majorCharacters + 1;
            $narrationChars = (int) ($chars * 0.8);
            $dialogueChars = (int) ($chars * 0.3);
            $sfx = (int) ceil($sceneCount * 1.5);
        }

        $lines = [];
        $add = function (CostCategory $category, int $units, string $unit, int $output = 0) use (&$lines) {
            if ($units <= 0) {
                return;
            }
            $lines[$category->value] = [
                'label' => $category->label(),
                'units' => $units,
                'unit' => $unit,
                'cost' => $this->price($category, $units, $output),
            ];
        };

        if (! $analysed) {
            $add(CostCategory::OpenAIText, (int) ($chars * 1.2 + 4000), 'توکن', (int) ($chars * 2.5 + 2000));
        }
        $add(CostCategory::OpenAIImage, $images, 'تصویر');
        $add(CostCategory::ElevenLabsVoiceDesign, $voiceDesigns, 'طراحی صدا');
        $add(CostCategory::ElevenLabsNarration, (int) $narrationChars, 'نویسه');
        $add(CostCategory::ElevenLabsDialogue, (int) $dialogueChars, 'نویسه');
        $add(CostCategory::ElevenLabsSoundEffects, $sfx, 'افکت');

        return ['lines' => $lines, 'total' => round(array_sum(array_column($lines, 'cost')), 4)];
    }

    /**
     * @return array{
     *     categories: array<string, array{label: string, calls: int, estimated: float, actual: float}>,
     *     total_estimated: float, total_actual: float, api_calls: int, failed_calls: int,
     *     images: int, tts_characters: int, audio_duration_ms: int
     * }
     */
    public function summary(StoryProject $project): array
    {
        $rows = $project->generationJobs()
            ->selectRaw('cost_category, status, count(*) as calls, sum(estimated_cost) as estimated, sum(actual_cost) as actual, sum(input_units) as units')
            ->groupBy('cost_category', 'status')
            ->get();

        $categories = [];
        foreach (CostCategory::cases() as $category) {
            $categories[$category->value] = ['label' => $category->label(), 'calls' => 0, 'estimated' => 0.0, 'actual' => 0.0];
        }

        $calls = 0;
        $failed = 0;
        $ttsChars = 0;
        foreach ($rows as $row) {
            $key = $row->cost_category instanceof CostCategory ? $row->cost_category->value : (string) $row->cost_category;
            if (! isset($categories[$key])) {
                continue;
            }
            $categories[$key]['calls'] += (int) $row->calls;
            $categories[$key]['estimated'] += (float) $row->estimated;
            $categories[$key]['actual'] += (float) $row->actual;
            $calls += (int) $row->calls;
            if ($row->status === GenerationStatus::Failed) {
                $failed += (int) $row->calls;
            }
            if (in_array($key, [CostCategory::ElevenLabsNarration->value, CostCategory::ElevenLabsDialogue->value], true) && $row->status === GenerationStatus::Succeeded) {
                $ttsChars += (int) $row->units;
            }
        }

        return [
            'categories' => $categories,
            'total_estimated' => round(array_sum(array_column($categories, 'estimated')), 4),
            'total_actual' => round(array_sum(array_column($categories, 'actual')), 4),
            'api_calls' => $calls,
            'failed_calls' => $failed,
            'images' => $project->assets()->whereIn('type', ['scene_image', 'character_ref_front', 'character_ref_three_quarter', 'character_ref_full_body', 'cover_art'])->count(),
            'tts_characters' => $ttsChars,
            'audio_duration_ms' => (int) $project->audioAssets()
                ->whereIn('type', [AudioAssetType::Narration->value, AudioAssetType::Dialogue->value, AudioAssetType::SoundEffect->value, AudioAssetType::Ambience->value])
                ->sum('duration_ms'),
        ];
    }
}
