<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AudioAssetType;
use App\Models\StoryStudio\Concerns\IsVersionedAsset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryAudioAsset extends Model
{
    use HasUuids, IsVersionedAsset;

    protected $table = 'story_audio_assets';

    protected $fillable = [
        'project_id',
        'scene_id',
        'dialogue_id',
        'character_id',
        'sound_effect_id',
        'voice_profile_id',
        'generation_job_id',
        'type',
        'slot',
        'version',
        'status',
        'provider',
        'model',
        'voice_id',
        'text',
        'tts_text',
        'disk',
        'file_path',
        'mime_type',
        'file_size',
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
            'type' => AudioAssetType::class,
            'status' => AssetStatus::class,
            'version' => 'integer',
            'selected' => 'boolean',
            'file_size' => 'integer',
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

    public function dialogue(): BelongsTo
    {
        return $this->belongsTo(StoryDialogue::class, 'dialogue_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(StoryCharacter::class, 'character_id');
    }

    public function soundEffect(): BelongsTo
    {
        return $this->belongsTo(StorySoundEffect::class, 'sound_effect_id');
    }

    public function voiceProfile(): BelongsTo
    {
        return $this->belongsTo(StoryVoiceProfile::class, 'voice_profile_id');
    }

    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(StoryGenerationJob::class, 'generation_job_id');
    }
}
