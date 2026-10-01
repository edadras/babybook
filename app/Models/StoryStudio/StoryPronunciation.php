<?php

namespace App\Models\StoryStudio;

use App\Enums\StoryStudio\PronunciationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryPronunciation extends Model
{
    use HasUuids;

    protected $table = 'story_pronunciations';

    protected $fillable = [
        'project_id',
        'word',
        'pronunciation',
        'type',
        'whole_word',
        'notes',
    ];

    /** Mirrors the column defaults so fresh instances behave like loaded ones. */
    protected $attributes = [
        'type' => 'general',
        'whole_word' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => PronunciationType::class,
            'whole_word' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'project_id');
    }

    /**
     * Global entries plus entries specific to the given project.
     */
    public function scopeApplicableTo(Builder $query, ?StoryProject $project): Builder
    {
        return $query->where(function (Builder $query) use ($project) {
            $query->whereNull('project_id');

            if ($project) {
                $query->orWhere('project_id', $project->getKey());
            }
        });
    }
}
