<?php

namespace App\Services\StoryStudio;

use App\Models\StoryStudio\StoryContinuityState;
use App\Models\StoryStudio\StoryScene;

/**
 * Tracks project-level visual continuity (props, outfits, weather, time of day,
 * objects introduced earlier) and renders it for the image prompt.
 */
class VisualContinuityService
{
    /**
     * Accumulated state from all scenes before (and including, when $inclusive) the given scene.
     *
     * @return array{props: list<string>, outfits: array<string, string>, weather: ?string, time_of_day: ?string, location: ?string, notes: list<string>}
     */
    public function stateAt(StoryScene $scene, bool $inclusive = true): array
    {
        $state = ['props' => [], 'outfits' => [], 'weather' => null, 'time_of_day' => null, 'location' => null, 'notes' => []];

        $scenes = $scene->project->scenes()
            ->where('sort_order', $inclusive ? '<=' : '<', $scene->sort_order)
            ->with('continuityState')
            ->get();

        foreach ($scenes as $previous) {
            /** @var StoryContinuityState|null $row */
            $row = $previous->continuityState;
            if (! $row) {
                continue;
            }
            $data = $row->state;
            $state['props'] = array_values(array_unique([...$state['props'], ...($data['props'] ?? [])]));
            $state['outfits'] = array_merge($state['outfits'], $data['outfits'] ?? []);
            $state['weather'] = $data['weather'] ?? $state['weather'];
            $state['time_of_day'] = $data['time_of_day'] ?? $state['time_of_day'];
            $state['location'] = $data['location'] ?? $state['location'];
            if (! empty($data['notes'])) {
                $state['notes'][] = $data['notes'];
            }
        }

        $state['notes'] = array_slice($state['notes'], -3);

        return $state;
    }

    public function block(StoryScene $scene): string
    {
        $state = $this->stateAt($scene);
        $lines = [];

        if ($state['props']) {
            $lines[] = 'Established props (keep exactly the same colors and design): '.implode(', ', $state['props']);
        }
        foreach ($state['outfits'] as $character => $change) {
            $lines[] = "Outfit state for {$character}: {$change}";
        }
        if ($state['weather']) {
            $lines[] = 'Weather: '.$state['weather'];
        }
        foreach ($state['notes'] as $note) {
            $lines[] = 'Note: '.$note;
        }

        return $lines ? "CONTINUITY:\n- ".implode("\n- ", $lines) : '';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function update(StoryScene $scene, array $state): void
    {
        StoryContinuityState::updateOrCreate(
            ['scene_id' => $scene->getKey()],
            ['project_id' => $scene->project_id, 'state' => $state, 'source' => 'manual'],
        );
    }
}
