<?php

namespace App\Services\StoryStudio\Providers\OpenAI;

use App\Services\StoryStudio\Contracts\ImageGenerationProviderInterface;
use App\Services\StoryStudio\Data\ImageResult;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\ProviderHttp;

/**
 * gpt-image generation. When reference images are given (approved character
 * sheets), the edits endpoint is used so characters stay visually consistent.
 */
class OpenAIImageProvider implements ImageGenerationProviderInterface
{
    public function __construct(private readonly OpenAIClient $client) {}

    public function generate(string $prompt, int $width, int $height, array $referenceImagePaths = []): ImageResult
    {
        $size = $this->size($width, $height);
        $startedAt = microtime(true);
        $references = array_values(array_filter($referenceImagePaths, 'is_file'));

        if ($references !== []) {
            $request = $this->client->request(300)->asMultipart();
            foreach (array_slice($references, 0, 8) as $index => $path) {
                $request = $request->attach('image[]', file_get_contents($path), 'reference_'.$index.'.png');
            }
            $response = $request->post('/images/edits', [
                ['name' => 'model', 'contents' => $this->model()],
                ['name' => 'prompt', 'contents' => $prompt],
                ['name' => 'size', 'contents' => $size],
                ['name' => 'quality', 'contents' => (string) config('story-studio.openai.image_quality', 'medium')],
                ['name' => 'input_fidelity', 'contents' => 'high'],
            ]);
            $operation = 'images.edits';
        } else {
            $response = $this->client->request(300)->post('/images/generations', [
                'model' => $this->model(),
                'prompt' => $prompt,
                'size' => $size,
                'quality' => config('story-studio.openai.image_quality', 'medium'),
                'n' => 1,
            ]);
            $operation = 'images.generations';
        }

        ProviderHttp::check('openai', $operation, $response, $startedAt);

        $b64 = data_get($response->json(), 'data.0.b64_json');
        if (! is_string($b64)) {
            throw new ProviderException('OpenAI تصویری برنگرداند.', 'openai', 200, true, 'empty_image');
        }

        [$w, $h] = array_map('intval', explode('x', $size));

        return new ImageResult(
            base64_decode($b64),
            'image/png',
            $w,
            $h,
            $this->model(),
            $response->header('x-request-id') ?: null,
            data_get($response->json(), 'data.0.revised_prompt'),
        );
    }

    public function supportsReferenceImages(): bool
    {
        return true;
    }

    /**
     * gpt-image supports 1024x1024, 1536x1024 (landscape) and 1024x1536 (portrait).
     */
    private function size(int $width, int $height): string
    {
        return match (true) {
            $width > $height => '1536x1024',
            $height > $width => '1024x1536',
            default => '1024x1024',
        };
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('story-studio.openai.image_model');
    }
}
