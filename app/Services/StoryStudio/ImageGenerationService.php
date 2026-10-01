<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\PipelineStage;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\Contracts\ImageGenerationProviderInterface;

class ImageGenerationService
{
    public function __construct(
        private readonly ImageGenerationProviderInterface $provider,
        private readonly GenerationGuard $guard,
        private readonly AssetStorageService $storage,
        private readonly ImageDirectorService $director,
        private readonly CharacterReferenceService $references,
        private readonly DependencyInvalidator $invalidator,
    ) {}

    /**
     * Generate one scene image version. $select=false creates a variation without replacing the current pick.
     */
    public function sceneImage(StoryScene $scene, string $nonce, bool $select = true): ?StoryAsset
    {
        $this->director->prepareScene($scene);
        $project = $scene->project;
        [$width, $height] = $project->frameSize();
        $prompt = (string) $scene->final_image_prompt;

        $referencePaths = $this->provider->supportsReferenceImages()
            ? $this->references->referenceImagePaths($scene, $this->storage)
            : [];

        $key = GenerationGuard::key('scene-image', $scene->getKey(), md5($prompt), md5(implode(',', array_map('basename', $referencePaths))), $nonce);

        $asset = $this->generate(
            $project, $key, PipelineStage::Images, 'image.scene', $scene, $prompt, $width, $height, $referencePaths,
            AssetType::SceneImage, $scene->imageSlot(), sprintf('scene_%02d_image', $scene->scene_number),
            ['scene_id' => $scene->getKey()], $select,
            ['director_prompt' => $scene->director_prompt, 'references' => count($referencePaths)],
        );

        if ($asset && $select) {
            $this->invalidator->imageSelected($scene);
        }

        return $asset;
    }

    public function characterReference(StoryCharacter $character, AssetType $view, string $nonce): ?StoryAsset
    {
        $project = $character->project;
        $prompt = $this->director->characterReferencePrompt($character, $view);
        $key = GenerationGuard::key('character-ref', $character->getKey(), $view->value, md5($prompt), $nonce);
        $size = $view === AssetType::CharacterFullBody ? [1024, 1536] : [1024, 1024];

        // Front view anchors the other two views when the provider supports references.
        $references = [];
        if ($view !== AssetType::CharacterFront && $this->provider->supportsReferenceImages()) {
            $front = $character->selectedReference(AssetType::CharacterFront);
            if ($front) {
                $references[] = $this->storage->localPath($front);
            }
        }

        $asset = $this->generate(
            $project, $key, PipelineStage::CharacterReferences, 'image.character_reference', $character, $prompt,
            $size[0], $size[1], $references, $view, $character->referenceSlot($view), $character->key.'_'.$view->value,
            ['character_id' => $character->getKey()], true, ['view' => $view->value],
        );

        if ($asset && $view === AssetType::CharacterFront) {
            $character->forceFill(['reference_asset_id' => $asset->getKey()])->save();
        }

        return $asset;
    }

    public function coverArt(StoryProject $project, string $nonce): ?StoryAsset
    {
        $prompt = $this->director->coverPrompt($project);
        [$width, $height] = $project->frameSize();
        $key = GenerationGuard::key('cover-art', $project->getKey(), md5($prompt), $nonce);

        return $this->generate(
            $project, $key, PipelineStage::Images, 'image.cover', $project, $prompt, $width, $height, [],
            AssetType::CoverArt, 'project:'.$project->getKey().':cover_art', 'cover_art', [], true, [],
        );
    }

    /**
     * @param  list<string>  $references
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function generate(
        StoryProject $project, string $key, PipelineStage $stage, string $operation, $target, string $prompt,
        int $width, int $height, array $references, AssetType $type, string $slot, string $baseName,
        array $attributes, bool $select, array $metadata,
    ): ?StoryAsset {
        $run = $this->guard->run(
            $project, $key, $stage, $operation, $target, $this->provider->name(), $this->provider->model(), CostCategory::OpenAIImage,
            ['prompt_chars' => mb_strlen($prompt), 'size' => "{$width}x{$height}", 'references' => count($references)],
            function () use ($project, $prompt, $width, $height, $references, $type, $slot, $baseName, $attributes, $select, $metadata) {
                $started = microtime(true);
                $result = $this->provider->generate($prompt, $width, $height, $references);
                $asset = $this->storage->storeVisual($project, $type, $slot, $baseName, $result->bytes, $result->extension(), array_merge($attributes, [
                    'provider' => $this->provider->name(),
                    'model' => $result->model,
                    'prompt' => $prompt,
                    'width' => $result->width,
                    'height' => $result->height,
                    'generation_time_ms' => (int) ((microtime(true) - $started) * 1000),
                    'estimated_cost' => app(CostTracker::class)->price(CostCategory::OpenAIImage, 1),
                    'actual_cost' => app(CostTracker::class)->price(CostCategory::OpenAIImage, 1),
                    'metadata' => array_merge($metadata, array_filter(['revised_prompt' => $result->revisedPrompt, 'request_id' => $result->requestId])),
                ]), $select);

                return new GenerationOutcome($asset, 1, 0, 'images', $result->requestId, ['asset_id' => $asset->getKey()]);
            },
        );

        $asset = $run->result ?? StoryAsset::find($run->ledger->response_summary['asset_id'] ?? null);
        if ($asset) {
            $asset->generation_job_id ??= $run->ledger->getKey();
            $asset->save();
            if ($run->reused && $select && ! $asset->selected) {
                $asset->select();
            }
            if ($asset->status !== AssetStatus::Ready) {
                $asset->forceFill(['status' => AssetStatus::Ready])->save();
            }
        }

        return $asset;
    }
}
