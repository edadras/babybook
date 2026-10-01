<?php

namespace App\Models\StoryStudio;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryContinuityState extends Model
{
    use HasUuids;

    protected $table = 'story_continuity_states';

    protected $fillable = [
        'project_id',
        'scene_id',
        'state',
        'source',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'source' => 'director',
    ];

    protected function casts(): array
    {
        return ['state' => 'array'];
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
