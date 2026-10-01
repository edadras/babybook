<?php

namespace App\Models\StoryStudio\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Asset rows are append-only: every generation is a new version within a
 * "slot" (e.g. "scene:{id}:scene_image"), and exactly one version per slot
 * may be selected.
 */
trait IsVersionedAsset
{
    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('selected', true);
    }

    public function scopeForSlot(Builder $query, string $slot): Builder
    {
        return $query->where('slot', $slot);
    }

    public static function nextVersion(string $projectId, string $slot): int
    {
        return (int) static::query()
            ->where('project_id', $projectId)
            ->where('slot', $slot)
            ->max('version') + 1;
    }

    /**
     * Mark this version as the selected one for its slot and unselect the rest.
     */
    public function select(): static
    {
        DB::transaction(function () {
            static::query()
                ->where('project_id', $this->project_id)
                ->where('slot', $this->slot)
                ->whereKeyNot($this->getKey())
                ->where('selected', true)
                ->update(['selected' => false]);

            $this->forceFill(['selected' => true])->save();
        });

        return $this;
    }

    /**
     * Human-friendly versioned name, e.g. "scene_03_image_v2".
     */
    public function versionLabel(): string
    {
        return $this->type->value.'_v'.$this->version;
    }

    public function url(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk($this->disk ?: config('story-studio.storage.disk'))->url($this->file_path);
    }
}
