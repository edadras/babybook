<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\Concerns\IsVersionedAsset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryAsset extends Model
{
    use HasUuids, IsVersionedAsset;

    protected $table = 'story_assets';

    protected $fillable = [
        'project_id',
        'scene_id',
        'character_id',
        'location_id',
        'generation_job_id',
        'type',
        'slot',
        'version',
        'status',
        'provider',
        'model',
        'prompt',
        'disk',
        'file_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'duration_ms',
        'metadata',
        'generation_time_ms',
        'estimated_cost',
        'actual_cost',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'pending',
        'selected' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'version' => 'integer',
            'selected' => 'boolean',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'metadata' => 'array',
            'generation_time_ms' => 'integer',
            'estimated_cost' => 'decimal:6',
            'actual_cost' => 'decimal:6',
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

    public function character(): BelongsTo
    {
        return $this->belongsTo(StoryCharacter::class, 'character_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StoryLocation::class, 'location_id');
    }

    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(StoryGenerationJob::class, 'generation_job_id');
    }
}
