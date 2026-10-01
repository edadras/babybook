<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\GenerationStatus;
use App\Enums\StoryStudio\RenderScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryRenderJob extends Model
{
    use HasUuids;

    protected $table = 'story_render_jobs';

    protected $fillable = [
        'project_id',
        'scene_id',
        'scope',
        'status',
        'progress',
        'input_snapshot',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'queued',
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'scope' => RenderScope::class,
            'status' => GenerationStatus::class,
            'progress' => 'integer',
            'input_snapshot' => 'array',
            'output_asset_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(StoryScene::class, 'scene_id');
    }
}
