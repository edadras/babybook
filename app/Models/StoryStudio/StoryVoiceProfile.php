<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\VoiceProfileStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryVoiceProfile extends Model
{
    use HasUuids;

    protected $table = 'story_voice_profiles';

    protected $fillable = [
        'project_id',
        'character_id',
        'provider',
        'provider_voice_id',
        'name',
        'description',
        'source',
        'generated_voice_id',
        'settings',
        'status',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'provider' => 'elevenlabs',
        'source' => 'library',
        'status' => 'candidate',
        'locked' => false,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => VoiceProfileStatus::class,
            'locked' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(StoryCharacter::class, 'character_id');
    }

    public function preview(): BelongsTo
    {
        return $this->belongsTo(StoryAudioAsset::class, 'preview_audio_id');
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(StoryAudioAsset::class, 'voice_profile_id');
    }
}
