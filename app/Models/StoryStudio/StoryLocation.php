<?php

namespace App\Models\StoryStudio;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryLocation extends Model
{
    use HasUuids;

    protected $table = 'story_locations';

    protected $fillable = [
        'key',
        'name',
        'description',
        'visual_description',
        'default_time_of_day',
        'sort_order',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(StoryScene::class, 'location_id');
    }

    public function referenceImage(): BelongsTo
    {
        return $this->belongsTo(StoryAsset::class, 'reference_asset_id');
    }
}
