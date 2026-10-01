<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\SubtitleMode;
use App\Enums\StoryStudio\Transition;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryProjectSetting extends Model
{
    use HasUuids;

    protected $table = 'story_project_settings';

    protected $fillable = [
        'default_motion',
        'default_transition',
        'transition_duration_ms',
        'music_volume',
        'default_music_audio_id',
        'ducking_db',
        'subtitle_mode',
        'burn_subtitles',
        'subtitle_font_size',
        'show_title_on_cover',
        'provider_overrides',
        'budget_limit',
    ];

    protected function casts(): array
    {
        return [
            'default_motion' => ImageMotion::class,
            'default_transition' => Transition::class,
            'transition_duration_ms' => 'integer',
            'music_volume' => 'float',
            'ducking_db' => 'integer',
            'subtitle_mode' => SubtitleMode::class,
            'burn_subtitles' => 'boolean',
            'subtitle_font_size' => 'integer',
            'show_title_on_cover' => 'boolean',
            'provider_overrides' => 'array',
            'budget_limit' => 'decimal:4',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }
}
