<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\PipelineStage;
use App\Models\StoryStudio\StoryProject;

/**
 * Live progress payload shown in the studio ("در حال ساخت کتاب...").
 */
class ProjectProgressService
{
    public function __construct(private readonly StoryPipelineService $pipeline) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(StoryProject $project): array
    {
        $report = $this->pipeline->report($project);
        $run = $this->pipeline->activeRun($project);

        $stages = [];
        foreach (PipelineStage::cases() as $stage) {
            $row = $report[$stage->value];
            $stages[] = ['key' => $stage->value, 'label' => $stage->label()] + ['state' => $row['status'], 'done' => $row['done'], 'total' => $row['total']];
        }

        return [
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_tone' => $project->status->tone(),
            'working' => $project->status->isWorking() && ! $project->paused_at,
            'paused' => (bool) $project->paused_at,
            'running' => (bool) $run,
            'progress' => $this->pipeline->overallProgress($report),
            'current_stage' => $project->current_stage?->value,
            'last_error' => $project->last_error,
            'gate' => $this->pipeline->pendingGate($project, $report),
            'signature' => md5(json_encode([$report, $project->status->value, $project->paused_at])),
            'stages' => $stages,
        ];
    }
}
