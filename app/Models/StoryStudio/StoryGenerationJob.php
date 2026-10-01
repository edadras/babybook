<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\PipelineStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoryGenerationJob extends Model
{
    use HasUuids;

    protected $table = 'story_generation_jobs';

    protected $fillable = [
        'project_id',
        'idempotency_key',
        'stage',
        'operation',
        'target_type',
        'target_id',
        'provider',
        'model',
        'status',
        'request_hash',
        'request_summary',
        'cost_category',
        'unit_type',
        'estimated_cost',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
        'input_units' => 0,
        'output_units' => 0,
    ];

    protected function casts(): array
    {
        return [
            'stage' => PipelineStage::class,
            'status' => GenerationStatus::class,
            'cost_category' => CostCategory::class,
            'attempts' => 'integer',
            'request_summary' => 'array',
            'response_summary' => 'array',
            'input_units' => 'integer',
            'output_units' => 'integer',
            'estimated_cost' => 'decimal:6',
            'actual_cost' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
