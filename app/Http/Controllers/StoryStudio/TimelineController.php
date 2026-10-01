<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\Transition;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\TimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TimelineController extends StudioController
{
    public function index(Request $request, StoryProject $project, TimelineService $timeline): View
    {
        $this->authorizeProject($request, $project, 'view');
        $data = $timeline->build($project);
        $selectedId = $request->query('scene', $data['scenes'][0]['id'] ?? null);

        return view('story-studio.workspace.timeline', [
            'project' => $project,
            'timeline' => $data,
            'selected' => collect($data['scenes'])->firstWhere('id', $selectedId),
            'scene' => $selectedId ? $project->scenes()->with(['dialogues.character', 'soundEffects'])->find($selectedId) : null,
        ]);
    }

    public function update(Request $request, StoryProject $project, StoryScene $scene, DependencyInvalidator $invalidator): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate([
            'duration_seconds' => ['nullable', 'numeric', 'min:2', 'max:180'],
            'transition' => ['required', Rule::enum(Transition::class)],
            'transition_duration_ms' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'motion' => ['required', Rule::enum(ImageMotion::class)],
            'music_volume' => ['nullable', 'numeric', 'min:0', 'max:1.5'],
            'music_fade_in_seconds' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'music_fade_out_seconds' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'dialogues' => ['nullable', 'array'],
            'dialogues.*.start_offset_seconds' => ['nullable', 'numeric', 'min:0', 'max:180'],
            'dialogues.*.volume' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'effects' => ['nullable', 'array'],
            'effects.*.start_offset_seconds' => ['nullable', 'numeric', 'min:0', 'max:180'],
            'effects.*.volume' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'effects.*.enabled' => ['nullable', 'boolean'],
        ]);

        $ms = fn ($seconds) => $seconds === null || $seconds === '' ? null : (int) round($seconds * 1000);
        $scene->forceFill([
            'duration_ms' => $ms($data['duration_seconds'] ?? null),
            'transition' => $data['transition'],
            'transition_duration_ms' => $data['transition_duration_ms'] ?? null,
            'motion' => $data['motion'],
            'music_volume' => $data['music_volume'] ?? null,
            'music_fade_in_ms' => $ms($data['music_fade_in_seconds'] ?? 0) ?? 0,
            'music_fade_out_ms' => $ms($data['music_fade_out_seconds'] ?? 0) ?? 0,
        ]);
        $audioChanged = $scene->isDirty(['duration_ms', 'music_volume', 'music_fade_in_ms', 'music_fade_out_ms']);
        $videoChanged = $scene->isDirty(['motion']);
        $finalChanged = $scene->isDirty(['transition', 'transition_duration_ms']);
        $scene->save();

        foreach ($data['dialogues'] ?? [] as $id => $row) {
            $dialogue = $scene->dialogues()->find($id);
            if ($dialogue) {
                $dialogue->fill(['start_offset_ms' => $ms($row['start_offset_seconds'] ?? null), 'volume' => $row['volume'] ?? 1]);
                $audioChanged = $audioChanged || $dialogue->isDirty();
                $dialogue->save();
            }
        }
        foreach ($data['effects'] ?? [] as $id => $row) {
            $effect = $scene->soundEffects()->find($id);
            if ($effect) {
                $effect->fill(['start_offset_ms' => $ms($row['start_offset_seconds'] ?? 0) ?? 0, 'volume' => $row['volume'] ?? $effect->volume, 'enabled' => (bool) ($row['enabled'] ?? false)]);
                $audioChanged = $audioChanged || $effect->isDirty();
                $effect->save();
            }
        }

        if ($audioChanged) {
            $invalidator->sceneOutputsChanged($scene);
        } elseif ($videoChanged) {
            $invalidator->sceneVideoChanged($scene);
        } elseif ($finalChanged) {
            $invalidator->finalChanged($project);
        }

        return redirect()->route('story-studio.projects.timeline', [$project, 'scene' => $scene->getKey()])->with('status', 'زمان‌بندی صحنه '.$scene->scene_number.' ذخیره شد.');
    }
}
