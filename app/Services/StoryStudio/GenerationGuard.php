<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\PipelineStage;
use App\Models\StoryStudio\StoryGenerationJob;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\Exceptions\GenerationInProgress;
use App\Services\StoryStudio\Support\ProviderHttp;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Application-level idempotency for billable generations.
 *
 * Each generation has a deterministic idempotency key. Before calling a
 * provider we claim the key under a row lock: a succeeded key is never
 * executed again (so a duplicated or retried queue job cannot charge twice),
 * and a key currently running elsewhere is not executed concurrently.
 */
class GenerationGuard
{
    /** Minutes after which a "running" claim is considered abandoned (crashed worker). */
    public const STALE_AFTER_MINUTES = 20;

    public function __construct(private readonly CostTracker $costs) {}

    public static function key(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $requestSummary  redacted, human-readable request info
     * @param  Closure(StoryGenerationJob): GenerationOutcome  $work
     */
    public function run(
        StoryProject $project,
        string $key,
        PipelineStage $stage,
        string $operation,
        ?Model $target,
        string $provider,
        string $model,
        CostCategory $category,
        array $requestSummary,
        Closure $work,
    ): GenerationRun {
        [$ledger, $state] = $this->claim($project, $key, $stage, $operation, $target, $provider, $model, $category, $requestSummary);

        if ($state === 'done') {
            return new GenerationRun($ledger, null, true);
        }

        try {
            $outcome = $work($ledger);
        } catch (Throwable $exception) {
            $ledger->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => property_exists($exception, 'errorCode') ? $exception->errorCode : class_basename($exception),
                'error_message' => ProviderHttp::redact($exception->getMessage()),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }

        $ledger->forceFill([
            'status' => GenerationStatus::Succeeded,
            'input_units' => $outcome->inputUnits,
            'output_units' => $outcome->outputUnits,
            'unit_type' => $outcome->unitType,
            'actual_cost' => $outcome->actualCost ?? $this->costs->price($category, $outcome->inputUnits, $outcome->outputUnits),
            'provider_request_id' => $outcome->requestId,
            'response_summary' => $outcome->summary,
            'error_code' => null,
            'error_message' => null,
            'finished_at' => now(),
        ])->save();

        return new GenerationRun($ledger, $outcome->result, false);
    }

    /**
     * @return array{0: StoryGenerationJob, 1: 'done'|'claimed'}
     */
    private function claim(StoryProject $project, string $key, PipelineStage $stage, string $operation, ?Model $target, string $provider, string $model, CostCategory $category, array $requestSummary): array
    {
        try {
            return DB::transaction(function () use ($project, $key, $stage, $operation, $target, $provider, $model, $category, $requestSummary) {
                $ledger = StoryGenerationJob::query()->where('idempotency_key', $key)->lockForUpdate()->first();

                if ($ledger?->status === GenerationStatus::Succeeded) {
                    return [$ledger, 'done'];
                }

                if ($ledger?->status === GenerationStatus::Running && $ledger->started_at?->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
                    throw new GenerationInProgress($key);
                }

                $ledger ??= new StoryGenerationJob(['idempotency_key' => $key]);
                $ledger->fill([
                    'project_id' => $project->getKey(),
                    'stage' => $stage,
                    'operation' => $operation,
                    'target_type' => $target?->getMorphClass(),
                    'target_id' => $target?->getKey(),
                    'provider' => $provider,
                    'model' => $model,
                    'cost_category' => $category,
                    'request_summary' => $requestSummary,
                ]);
                $ledger->forceFill([
                    'status' => GenerationStatus::Running,
                    'attempts' => $ledger->attempts + 1,
                    'started_at' => now(),
                    'finished_at' => null,
                ])->save();

                return [$ledger, 'claimed'];
            });
        } catch (QueryException $exception) {
            // Lost an insert race for the same key: another worker owns it.
            throw new GenerationInProgress($key, $exception);
        }
    }
}
