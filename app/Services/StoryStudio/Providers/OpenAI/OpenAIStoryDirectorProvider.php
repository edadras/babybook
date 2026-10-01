<?php

namespace App\Services\StoryStudio\Providers\OpenAI;

use App\Services\StoryStudio\Contracts\StoryDirectorProviderInterface;
use App\Services\StoryStudio\Data\StructuredResult;
use App\Services\StoryStudio\Exceptions\InvalidAiOutputException;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Support\ProviderHttp;

/**
 * OpenAI Responses API with strict JSON-schema structured output.
 */
class OpenAIStoryDirectorProvider implements StoryDirectorProviderInterface
{
    public function __construct(private readonly OpenAIClient $client) {}

    public function generateStructured(string $system, string $user, array $schema, string $schemaName): StructuredResult
    {
        $startedAt = microtime(true);
        $response = $this->client->request()->post('/responses', [
            'model' => $this->model(),
            'instructions' => $system,
            'input' => $user,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        ProviderHttp::check('openai', 'responses', $response, $startedAt);
        $json = $response->json();

        $text = null;
        foreach ($json['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new ProviderException('OpenAI از پردازش این متن خودداری کرد: '.($content['refusal'] ?? ''), 'openai', 200, false, 'refusal');
                }
                if (($content['type'] ?? null) === 'output_text') {
                    $text = $content['text'];
                }
            }
        }

        if (($json['status'] ?? 'completed') === 'incomplete') {
            throw new ProviderException('پاسخ OpenAI ناقص ماند ('.data_get($json, 'incomplete_details.reason', 'unknown').').', 'openai', 200, true, 'incomplete');
        }

        $data = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($data)) {
            throw new InvalidAiOutputException(['response is not valid JSON']);
        }

        return new StructuredResult(
            $data,
            (string) ($json['model'] ?? $this->model()),
            (int) data_get($json, 'usage.input_tokens', 0),
            (int) data_get($json, 'usage.output_tokens', 0),
            $response->header('x-request-id') ?: ($json['id'] ?? null),
        );
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('story-studio.openai.director_model');
    }
}
