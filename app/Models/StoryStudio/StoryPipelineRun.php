<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\PipelineStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryPipelineRun extends Model
{
    use HasUuids;

    protected $table = 'story_pipeline_runs';

    protected $fillable = [
        'project_id',
        'mode',
        'status',
        'current_stage',
        'stage_states',
        'estimated_cost',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'running',
    ];

    protected function casts(): array
    {
        return [
            'mode' => GenerationMode::class,
            'current_stage' => PipelineStage::class,
            'stage_states' => 'array',
            'estimated_cost' => 'decimal:6',
            'cost_confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }
}
