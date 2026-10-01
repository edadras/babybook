<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\Transition;
use Database\Factories\StoryStudio\StorySceneFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoryScene extends Model
{
    /** @use HasFactory<StorySceneFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'story_scenes';

    protected $fillable = [
        'location_id',
        'scene_number',
        'sort_order',
        'title',
        'time_of_day',
        'duration_estimate_ms',
        'duration_ms',
        'narration_original',
        'narration_tts',
        'narration_en',
        'visual',
        'director_prompt',
        'final_image_prompt',
        'audio_plan',
        'motion',
        'transition',
        'transition_duration_ms',
        'music_mood',
        'music_volume',
        'music_fade_in_ms',
        'music_fade_out_ms',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'status' => 'pending',
        'motion' => 'auto',
        'transition' => 'crossfade',
        'prompt_edited' => false,
        'sort_order' => 0,
        'music_fade_in_ms' => 0,
        'music_fade_out_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'scene_number' => 'integer',
            'sort_order' => 'integer',
            'duration_estimate_ms' => 'integer',
            'duration_ms' => 'integer',
            'visual' => 'array',
            'prompt_edited' => 'boolean',
            'audio_plan' => 'array',
            'motion' => ImageMotion::class,
            'transition' => Transition::class,
            'transition_duration_ms' => 'integer',
            'music_volume' => 'float',
            'music_fade_in_ms' => 'integer',
            'music_fade_out_ms' => 'integer',
            'status' => AssetStatus::class,
            'stale_flags' => 'array',
            'work_state' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StoryLocation::class, 'location_id');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(StoryCharacter::class, 'story_scene_characters', 'scene_id', 'character_id')
            ->withPivot(['action', 'position'])
            ->withTimestamps();
    }

    public function dialogues(): HasMany
    {
        return $this->hasMany(StoryDialogue::class, 'scene_id')->orderBy('sort_order');
    }

    public function soundEffects(): HasMany
    {
        return $this->hasMany(StorySoundEffect::class, 'scene_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(StoryAsset::class, 'scene_id');
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(StoryAudioAsset::class, 'scene_id');
    }

    public function continuityState(): HasOne
    {
        return $this->hasOne(StoryContinuityState::class, 'scene_id');
    }

    public function musicTrack(): BelongsTo
    {
        return $this->belongsTo(StoryAudioAsset::class, 'music_audio_id');
    }

    public function selectedImage(): ?StoryAsset
    {
        return $this->assets()->where('type', AssetType::SceneImage)->selected()->first();
    }

    public function effectiveDurationMs(): int
    {
        return $this->duration_ms ?? $this->duration_estimate_ms ?? 8000;
    }

    public function workState(string $key): AssetStatus
    {
        return AssetStatus::tryFrom($this->work_state[$key] ?? '') ?? AssetStatus::Pending;
    }

    /**
     * Atomically merges work-state keys (re-reads first so concurrent jobs don't clobber each other).
     *
     * @param  array<string, AssetStatus>  $states
     */
    public function setWorkState(array $states): void
    {
        $fresh = static::query()->whereKey($this->getKey())->value('work_state');
        $current = is_string($fresh) ? (json_decode($fresh, true) ?: []) : ($fresh ?? []);

        foreach ($states as $key => $status) {
            $current[$key] = $status->value;
        }

        $this->forceFill(['work_state' => $current])->save();
    }

    public function selectedAudio(AudioAssetType $type): ?StoryAudioAsset
    {
        return $this->audioAssets()->where('type', $type)->selected()->latest('version')->first();
    }

    public function slot(string $kind): string
    {
        return 'scene:'.$this->getKey().':'.$kind;
    }

    public function imageSlot(): string
    {
        return 'scene:'.$this->getKey().':'.AssetType::SceneImage->value;
    }
}
