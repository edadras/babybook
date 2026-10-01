<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\PipelineStage;
use App\Models\StoryStudio\StoryPipelineRun;
use App\Models\StoryStudio\StoryProject;

/**
 * Builds the live progress payload shown in the studio ("در حال ساخت کتاب...").
 * Stage counters are maintained by the pipeline run; this class only reads them.
 */
class ProjectProgressService
{
    /**
     * @return array{
     *     status: string, status_label: string, status_tone: string, working: bool,
     *     progress: int, current_stage: ?string,
     *     stages: list<array{key: string, label: string, state: string, done: int, total: int}>
     * }
     */
    public function summarize(StoryProject $project): array
    {
        /** @var StoryPipelineRun|null $run */
        $run = $project->pipelineRuns()->latest('created_at')->first();
        $states = $run?->stage_states ?? [];

        $stages = [];
        $weightTotal = 0;
        $weightDone = 0.0;

        foreach (PipelineStage::cases() as $stage) {
            $state = $states[$stage->value] ?? [];
            $done = (int) ($state['done'] ?? 0);
            $total = (int) ($state['total'] ?? 0);
            $status = $state['status'] ?? 'pending';

            $fraction = match (true) {
                $status === 'done' => 1.0,
                $total > 0 => min(1, $done / $total),
                default => 0.0,
            };

            $weightTotal += $stage->weight();
            $weightDone += $fraction * $stage->weight();

            $stages[] = [
                'key' => $stage->value,
                'label' => $stage->label(),
                'state' => $status,
                'done' => $done,
                'total' => $total,
            ];
        }

        $progress = $run ? (int) round($weightDone / max(1, $weightTotal) * 100) : $project->progress;

        return [
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_tone' => $project->status->tone(),
            'working' => $project->status->isWorking(),
            'progress' => $progress,
            'current_stage' => $run?->current_stage?->value ?? $project->current_stage?->value,
            'stages' => $stages,
        ];
    }
}
