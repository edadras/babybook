<?php

namespace App\Services\StoryStudio\Contracts;

use App\Services\StoryStudio\Data\ImageResult;

interface ImageGenerationProviderInterface
{
    /**
     * @param  list<string>  $referenceImagePaths  absolute local paths; ignored when unsupported
     */
    public function generate(string $prompt, int $width, int $height, array $referenceImagePaths = []): ImageResult;

    public function supportsReferenceImages(): bool;

    public function name(): string;

    public function model(): string;
}
