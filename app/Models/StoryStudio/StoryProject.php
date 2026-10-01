<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\AspectRatio;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\NarrationStyle;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\Resolution;
use App\Enums\StoryStudio\StoryCategory;
use App\Enums\StoryStudio\TargetAge;
use App\Enums\StoryStudio\VisualStyle;
use App\Models\User;
use Database\Factories\StoryStudio\StoryProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoryProject extends Model
{
    /** @use HasFactory<StoryProjectFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'story_projects';

    protected $fillable = [
        'user_id',
        'title',
        'original_text',
        'language',
        'target_age',
        'category',
        'visual_style',
        'custom_visual_instructions',
        'narration_style',
        'aspect_ratio',
        'resolution',
        'mode',
    ];

    protected $attributes = [
        'status' => 'draft',
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'target_age' => TargetAge::class,
            'category' => StoryCategory::class,
            'visual_style' => VisualStyle::class,
            'narration_style' => NarrationStyle::class,
            'aspect_ratio' => AspectRatio::class,
            'resolution' => Resolution::class,
            'mode' => GenerationMode::class,
            'status' => ProjectStatus::class,
            'current_stage' => PipelineStage::class,
            'progress' => 'integer',
            'story_metadata' => 'array',
            'style_bible' => 'array',
            'safety_report' => 'array',
            'safety_acknowledged_at' => 'datetime',
            'duration_ms' => 'integer',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (StoryProject $project) {
            $project->settings()->create();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(StoryProjectSetting::class, 'project_id');
    }

    public function characters(): HasMany
    {
        return $this->hasMany(StoryCharacter::class, 'project_id')->orderBy('sort_order');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StoryLocation::class, 'project_id')->orderBy('sort_order');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(StoryScene::class, 'project_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(StoryAsset::class, 'project_id');
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(StoryAudioAsset::class, 'project_id');
    }

    public function voiceProfiles(): HasMany
    {
        return $this->hasMany(StoryVoiceProfile::class, 'project_id');
    }

    public function pronunciations(): HasMany
    {
        return $this->hasMany(StoryPronunciation::class, 'project_id');
    }

    public function generationJobs(): HasMany
    {
        return $this->hasMany(StoryGenerationJob::class, 'project_id');
    }

    public function renderJobs(): HasMany
    {
        return $this->hasMany(StoryRenderJob::class, 'project_id');
    }

    public function continuityStates(): HasMany
    {
        return $this->hasMany(StoryContinuityState::class, 'project_id');
    }

    public function pipelineRuns(): HasMany
    {
        return $this->hasMany(StoryPipelineRun::class, 'project_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(StoryAsset::class, 'cover_asset_id');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        // Admins share one studio; per-owner scoping can be enabled in config.
        if ($user && config('story-studio.access.scope_to_owner')) {
            $query->where('user_id', $user->getKey());
        }

        return $query;
    }

    public function finalVideo(): ?StoryAsset
    {
        return $this->assets()->where('type', AssetType::FinalVideo)->selected()->latest('version')->first();
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, [ProjectStatus::Analyzing, ProjectStatus::Rendering], true);
    }

    public function frameSize(): array
    {
        return $this->aspect_ratio->dimensions($this->resolution);
    }
}
