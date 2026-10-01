<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use Database\Factories\StoryStudio\StoryCharacterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoryCharacter extends Model
{
    /** @use HasFactory<StoryCharacterFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'story_characters';

    /**
     * Character Bible fields that define the locked visual identity.
     */
    public const VISUAL_IDENTITY_FIELDS = [
        'visual_description',
        'clothing',
        'hair',
        'face',
        'colors',
        'height_relation',
        'special_features',
    ];

    protected $fillable = [
        'key',
        'name',
        'role',
        'is_narrator',
        'is_major',
        'age_group',
        'gender',
        'personality',
        'visual_description',
        'clothing',
        'hair',
        'face',
        'colors',
        'height_relation',
        'special_features',
        'reference_prompt',
        'voice_description',
        'sort_order',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'pending',
        'voice_status' => 'pending',
        'is_narrator' => false,
        'is_major' => true,
        'character_locked' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_narrator' => 'boolean',
            'is_major' => 'boolean',
            'colors' => 'array',
            'character_locked' => 'boolean',
            'locked_at' => 'datetime',
            'status' => AssetStatus::class,
            'voice_status' => AssetStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function voiceProfile(): BelongsTo
    {
        return $this->belongsTo(StoryVoiceProfile::class, 'voice_profile_id');
    }

    public function voiceCandidates(): HasMany
    {
        return $this->hasMany(StoryVoiceProfile::class, 'character_id');
    }

    public function referenceImage(): BelongsTo
    {
        return $this->belongsTo(StoryAsset::class, 'reference_asset_id');
    }

    public function referenceAssets(): HasMany
    {
        return $this->hasMany(StoryAsset::class, 'character_id');
    }

    public function dialogues(): HasMany
    {
        return $this->hasMany(StoryDialogue::class, 'character_id');
    }

    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(StoryScene::class, 'story_scene_characters', 'character_id', 'scene_id')
            ->withPivot(['action', 'position'])
            ->withTimestamps();
    }

    public function referenceSlot(AssetType $view): string
    {
        return 'character:'.$this->getKey().':'.$view->value;
    }

    public function selectedReference(AssetType $view): ?StoryAsset
    {
        return $this->referenceAssets()->where('type', $view)->selected()->latest('version')->first();
    }

    public function needsReferenceImages(): bool
    {
        return $this->is_major && ! $this->is_narrator;
    }

    public function lock(): void
    {
        $this->forceFill(['character_locked' => true, 'locked_at' => now()])->save();
    }

    public function unlock(): void
    {
        $this->forceFill(['character_locked' => false, 'locked_at' => null])->save();
    }
}
