<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryDialogue extends Model
{
    use HasUuids;

    protected $table = 'story_dialogues';

    protected $fillable = [
        'character_id',
        'sort_order',
        'text_original',
        'text_tts',
        'text_en',
        'emotion',
        'energy',
        'pace',
        'delivery',
        'provider_tags',
        'start_offset_ms',
        'volume',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'pending',
        'sort_order' => 0,
        'volume' => 1.0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'provider_tags' => 'array',
            'start_offset_ms' => 'integer',
            'volume' => 'float',
            'status' => AssetStatus::class,
        ];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(StoryScene::class, 'scene_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(StoryCharacter::class, 'character_id');
    }

    public function selectedAudio(): ?StoryAudioAsset
    {
        return $this->audioAssets()->selected()->latest('version')->first();
    }

    public function slot(): string
    {
        return 'dialogue:'.$this->getKey();
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(StoryAudioAsset::class, 'dialogue_id');
    }
}
