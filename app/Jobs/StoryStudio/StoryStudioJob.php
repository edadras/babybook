<?php

namespace App\Jobs\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\Exceptions\GenerationInProgress;
use App\Services\StoryStudio\Exceptions\ProviderException;
use App\Services\StoryStudio\Exceptions\ProviderRateLimited;
use App\Services\StoryStudio\StoryPipelineService;
use App\Services\StoryStudio\Support\ProviderHttp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for all Story Studio jobs: retryable, pause/cancel aware, idempotent
 * (the GenerationGuard prevents double charging), and it notifies the
 * pipeline orchestrator when done.
 */
abstract class StoryStudioJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public string $projectId,
        public ?string $targetId = null,
        public string $nonce = 'auto',
    ) {
        $this->onQueue(config('story-studio.queues.'.$this->queueName()));
        if ($connection = config('story-studio.queues.connection')) {
            $this->onConnection($connection);
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 90, 300];
    }

    abstract protected function queueName(): string;

    abstract protected function process(StoryProject $project): void;

    /**
     * Record the failure on the work item (state => failed).
     */
    abstract protected function markFailed(StoryProject $project): void;

    /**
     * Put the work item back to "pending" when the job is cancelled before running.
     */
    protected function markCancelled(StoryProject $project): void {}

    public function handle(): void
    {
        $project = StoryProject::find($this->projectId);

        if (! $project) {
            return;
        }

        if ($project->cancelled_at) {
            $this->markCancelled($project);

            return;
        }

        if ($project->paused_at) {
            $this->release(60);

            return;
        }

        try {
            $this->process($project);
        } catch (GenerationInProgress) {
            $this->release(30);

            return;
        } catch (ProviderRateLimited $exception) {
            $this->release($exception->retryAfterSeconds + 1);

            return;
        } catch (ProviderException $exception) {
            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        app(StoryPipelineService::class)->afterJob($project->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $project = StoryProject::find($this->projectId);
        if (! $project) {
            return;
        }

        Log::channel(config('story-studio.log_channel'))->error('story-studio.job_failed', [
            'job' => static::class,
            'project_id' => $this->projectId,
            'target_id' => $this->targetId,
            'error' => $exception ? ProviderHttp::redact($exception->getMessage()) : null,
        ]);

        $this->markFailed($project);
        $project->forceFill(['last_error' => $exception ? ProviderHttp::redact($exception->getMessage()) : 'خطای نامشخص'])->save();
        app(StoryPipelineService::class)->afterJob($project->fresh());
    }
}
