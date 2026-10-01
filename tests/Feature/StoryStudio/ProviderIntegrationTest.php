<?php

namespace Tests\Feature\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\Contracts\StoryDirectorProviderInterface;
use App\Services\StoryStudio\Data\StructuredResult;
use App\Services\StoryStudio\Exceptions\InvalidAiOutputException;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Providers\ElevenLabs\ElevenLabsSoundEffectProvider;
use App\Services\StoryStudio\Providers\ElevenLabs\ElevenLabsSpeechProvider;
use App\Services\StoryStudio\Providers\ElevenLabs\ElevenLabsVoiceDesignProvider;
use App\Services\StoryStudio\Providers\Fake\FakeStoryDirectorProvider;
use App\Services\StoryStudio\Providers\OpenAI\OpenAIImageProvider;
use App\Services\StoryStudio\Providers\OpenAI\OpenAIStoryDirectorProvider;
use App\Services\StoryStudio\Schema\StorySchemas;
use App\Services\StoryStudio\StoryDirectorService;
use App\Services\StoryStudio\Support\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openai.key' => 'sk-test-SECRET-123456789',
            'services.elevenlabs.key' => 'el-SECRET-key',
            'story-studio.http.retries' => 0,
        ]);
    }

    private function analysis(): array
    {
        return (new FakeStoryDirectorProvider)->generateStructured('s', '<story>'.PipelineEndToEndTest::STORY.'</story>', [], 'story_analysis')->data;
    }

    public function test_openai_director_uses_strict_structured_output(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_1', 'model' => 'gpt-5', 'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->analysis())]]]],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 3400],
        ])]);

        $result = app(OpenAIStoryDirectorProvider::class)->generateStructured('system', 'user', StorySchemas::analysis(), 'story_analysis');

        $this->assertSame(1200, $result->inputTokens);
        $this->assertSame('hero', $result->data['characters'][0]['id']);
        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer sk-test-SECRET-123456789')
                && $request['text']['format']['type'] === 'json_schema'
                && $request['text']['format']['strict'] === true
                && $request['instructions'] === 'system';
        });
    }

    public function test_refusals_are_not_retried_and_not_stored(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'cannot help']]]],
        ])]);

        try {
            app(OpenAIStoryDirectorProvider::class)->generateStructured('s', 'u', [], 'x');
            $this->fail('expected exception');
        } catch (ProviderException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame('refusal', $exception->errorCode);
        }
    }

    public function test_provider_errors_are_logged_without_secrets(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->andReturnSelf();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-test-SECRET-123456789', 'code' => 'invalid_api_key']], 401)]);

        try {
            app(OpenAIStoryDirectorProvider::class)->generateStructured('s', 'u', [], 'x');
        } catch (ProviderException $exception) {
            $this->assertStringNotContainsString('SECRET-123456789', $exception->getMessage());
        }

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'SECRET-123456789'));
    }

    public function test_invalid_ai_json_is_rejected_and_nothing_is_stored(): void
    {
        $data = $this->analysis();
        unset($data['scenes'][0]['visual']['image_prompt']);
        $data['scenes'][0]['audio']['music_mood'] = 'heavy metal';

        $errors = app(JsonSchemaValidator::class)->validate($data, StorySchemas::analysis());
        $this->assertNotEmpty($errors);

        $this->app->bind(StoryDirectorProviderInterface::class, fn () => new class($data) extends FakeStoryDirectorProvider
        {
            public function __construct(private array $payload) {}

            public function generateStructured(string $system, string $user, array $schema, string $schemaName): StructuredResult
            {
                return new StructuredResult($this->payload, 'bad-model');
            }
        });

        $project = StoryProject::factory()->create();
        $this->expectException(InvalidAiOutputException::class);

        try {
            app(StoryDirectorService::class)->analyzeAndStore($project, 'n1');
        } finally {
            $this->assertSame(0, $project->scenes()->count());
            $this->assertNull($project->fresh()->story_metadata);
        }
    }

    public function test_openai_images_use_reference_sheets_when_available(): void
    {
        Http::fake(['api.openai.com/v1/images/*' => Http::response(['data' => [['b64_json' => base64_encode('PNGDATA')]]])]);
        $reference = tempnam(sys_get_temp_dir(), 'ref');
        file_put_contents($reference, 'REF');

        $result = app(OpenAIImageProvider::class)->generate('prompt', 1920, 1080, [$reference]);

        $this->assertSame('PNGDATA', $result->bytes);
        $this->assertSame(1536, $result->width);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits') && $request->isMultipart());
    }

    public function test_elevenlabs_speech_voice_design_and_sfx(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('MP3BYTES', 200, ['x-character-count' => '17', 'request-id' => 'req-1']),
            'api.elevenlabs.io/v1/text-to-voice/design*' => Http::response(['previews' => [
                ['generated_voice_id' => 'gen1', 'audio_base_64' => base64_encode('A'), 'duration_secs' => 2.5],
                ['generated_voice_id' => 'gen2', 'audio_base_64' => base64_encode('B'), 'duration_secs' => 2.1],
            ]]),
            'api.elevenlabs.io/v1/text-to-voice' => Http::response(['voice_id' => 'voice_final']),
            'api.elevenlabs.io/v1/sound-generation*' => Http::response('SFX'),
        ]);

        $audio = app(ElevenLabsSpeechProvider::class)->synthesize('[worried] مامان! کجایی؟', 'voice_abc', ['stability' => 0.4]);
        $this->assertSame('MP3BYTES', $audio->bytes);
        $this->assertSame(17, $audio->characters);

        $previews = app(ElevenLabsVoiceDesignProvider::class)->design('Warm Persian storyteller', str_repeat('قصه ', 40));
        $this->assertCount(2, $previews);
        $this->assertSame(2500, $previews[0]->durationMs);
        $this->assertSame('voice_final', app(ElevenLabsVoiceDesignProvider::class)->createVoice('gen1', 'Narrator', 'Warm'));

        $this->assertSame('SFX', app(ElevenLabsSoundEffectProvider::class)->generate('birds', 3.0)->bytes);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/text-to-speech/voice_abc')
            && $request->hasHeader('xi-api-key', 'el-SECRET-key')
            && $request['text'] === '[worried] مامان! کجایی؟'
            && $request['model_id'] === config('story-studio.elevenlabs.tts_model'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sound-generation') && $request['duration_seconds'] === 3.0);
    }

    public function test_missing_api_keys_fail_clearly(): void
    {
        config(['services.openai.key' => null]);
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        app(OpenAIStoryDirectorProvider::class)->generateStructured('s', 'u', [], 'x');
    }
}
