<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\SoundKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorySoundEffect extends Model
{
    use HasUuids;

    protected $table = 'story_sound_effects';

    protected $fillable = [
        'kind',
        'description',
        'prompt',
        'enabled',
        'start_offset_ms',
        'duration_ms',
        'volume',
        'loop',
        'sort_order',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'kind' => 'sfx',
        'status' => 'pending',
        'enabled' => true,
        'loop' => false,
        'start_offset_ms' => 0,
        'volume' => 0.6,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => SoundKind::class,
            'enabled' => 'boolean',
            'start_offset_ms' => 'integer',
            'duration_ms' => 'integer',
            'volume' => 'float',
            'loop' => 'boolean',
            'sort_order' => 'integer',
            'status' => AssetStatus::class,
        ];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(StoryScene::class, 'scene_id');
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(StoryAudioAsset::class, 'sound_effect_id');
    }
}
