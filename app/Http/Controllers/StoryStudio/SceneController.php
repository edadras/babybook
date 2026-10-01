<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\Transition;
use App\Jobs\StoryStudio\GenerateSceneDialogueJob;
use App\Jobs\StoryStudio\GenerateSceneImageJob;
use App\Jobs\StoryStudio\GenerateSceneNarrationJob;
use App\Jobs\StoryStudio\MixSceneAudioJob;
use App\Jobs\StoryStudio\RenderSceneJob;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\ImageDirectorService;
use App\Services\StoryStudio\PronunciationDictionaryService;
use App\Services\StoryStudio\Schema\StorySchemas;
use App\Services\StoryStudio\VisualContinuityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SceneController extends StudioController
{
    public function __construct(
        private readonly DependencyInvalidator $invalidator,
        private readonly ImageDirectorService $imageDirector,
        private readonly PronunciationDictionaryService $pronunciation,
    ) {}

    public function index(Request $request, StoryProject $project): View
    {
        $this->authorizeProject($request, $project, 'view');

        return view('story-studio.workspace.storyboard', [
            'project' => $project,
            'scenes' => $project->scenes()->with([
                'location', 'characters', 'dialogues.character', 'soundEffects', 'continuityState',
                'assets' => fn ($q) => $q->whereIn('type', [AssetType::SceneImage, AssetType::SceneVideo])->orderByDesc('version'),
                'audioAssets' => fn ($q) => $q->where('selected', true),
            ])->get(),
            'characters' => $project->characters()->where('is_narrator', false)->get(),
            'locations' => $project->locations,
            'focus' => $request->query('scene'),
        ]);
    }

    public function update(Request $request, StoryProject $project, StoryScene $scene, VisualContinuityService $continuity): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        if ($request->has('character_ids')) {
            // An empty hidden value lets the form clear every character.
            $request->merge(['character_ids' => array_values(array_filter((array) $request->input('character_ids')))]);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'narration_original' => ['nullable', 'string', 'max:10000'],
            'narration_en' => ['nullable', 'string', 'max:10000'],
            'location_id' => ['nullable', Rule::exists('story_locations', 'id')->where('project_id', $project->getKey())],
            'time_of_day' => ['nullable', 'string', 'max:32'],
            'visual.description' => ['nullable', 'string', 'max:3000'],
            'visual.shot_type' => ['nullable', 'string', 'max:120'],
            'visual.camera_angle' => ['nullable', 'string', 'max:120'],
            'visual.lighting' => ['nullable', 'string', 'max:255'],
            'visual.mood' => ['nullable', 'string', 'max:255'],
            'visual.composition' => ['nullable', 'string', 'max:500'],
            'director_prompt' => ['nullable', 'string', 'max:4000'],
            'final_image_prompt' => ['nullable', 'string', 'max:8000'],
            'reset_prompt' => ['sometimes', 'boolean'],
            'motion' => ['nullable', Rule::enum(ImageMotion::class)],
            'transition' => ['nullable', Rule::enum(Transition::class)],
            'duration_seconds' => ['nullable', 'numeric', 'min:2', 'max:180'],
            'music_mood' => ['nullable', Rule::in(StorySchemas::MUSIC_MOODS)],
            'character_ids' => ['nullable', 'array'],
            'character_ids.*' => [Rule::exists('story_characters', 'id')->where('project_id', $project->getKey())],
            'continuity_props' => ['nullable', 'string', 'max:1000'],
        ]);

        $visualChanged = false;
        $narrationChanged = false;
        $timingChanged = false;

        if ($request->has('narration_original') && $data['narration_original'] !== $scene->narration_original) {
            $scene->narration_original = $data['narration_original'];
            $scene->narration_tts = $this->pronunciation->normalize((string) $data['narration_original'], $project);
            $narrationChanged = true;
        }
        foreach (['title', 'narration_en'] as $field) {
            if ($request->has($field)) {
                $scene->{$field} = $data[$field];
            }
        }
        foreach (['location_id', 'time_of_day', 'director_prompt'] as $field) {
            if ($request->has($field) && $scene->{$field} !== $data[$field]) {
                $scene->{$field} = $data[$field];
                $visualChanged = true;
            }
        }
        if ($request->has('visual')) {
            $visual = array_merge($scene->visual ?? [], array_filter($data['visual'] ?? [], fn ($v) => $v !== null));
            if ($visual !== $scene->visual) {
                $scene->visual = $visual;
                $visualChanged = true;
            }
        }
        if ($request->boolean('reset_prompt')) {
            $scene->prompt_edited = false;
            $visualChanged = true;
        } elseif ($request->filled('final_image_prompt') && $data['final_image_prompt'] !== $scene->final_image_prompt) {
            $scene->final_image_prompt = $data['final_image_prompt'];
            $scene->prompt_edited = true;
            $visualChanged = true;
        }
        if ($request->has('motion') && $data['motion'] && $scene->motion->value !== $data['motion']) {
            $scene->motion = ImageMotion::from($data['motion']);
            $timingChanged = true;
        }
        if ($request->has('transition') && $data['transition'] && $scene->transition->value !== $data['transition']) {
            $scene->transition = Transition::from($data['transition']);
            $this->invalidator->finalChanged($project);
        }
        if ($request->has('duration_seconds')) {
            $duration = $data['duration_seconds'] !== null ? (int) round($data['duration_seconds'] * 1000) : null;
            if ($duration !== $scene->duration_ms) {
                $scene->duration_ms = $duration;
                $timingChanged = true;
            }
        }
        if ($request->has('music_mood')) {
            $scene->music_mood = $data['music_mood'];
        }
        $scene->save();

        if ($request->has('character_ids')) {
            $before = $scene->characters()->pluck('story_characters.id')->sort()->values()->all();
            $after = collect($data['character_ids'] ?? [])->sort()->values()->all();
            if ($before !== $after) {
                $scene->characters()->sync($after);
                $visualChanged = true;
            }
        }
        if ($request->has('continuity_props')) {
            $state = $scene->continuityState?->state ?? [];
            $state['props'] = array_values(array_filter(array_map('trim', explode(',', str_replace('،', ',', (string) $data['continuity_props'])))));
            $continuity->update($scene, $state);
        }

        if ($visualChanged) {
            $this->imageDirector->prepareScene($scene->fresh());
            $this->invalidator->visualChanged($scene);
        }
        if ($narrationChanged) {
            $this->invalidator->narrationChanged($scene);
        }
        if ($timingChanged) {
            $this->invalidator->sceneOutputsChanged($scene);
        }

        return $this->done('صحنه '.$scene->scene_number.' ذخیره شد.'.($visualChanged || $narrationChanged ? ' بخش‌های وابسته برای بازسازی علامت خوردند.' : ''));
    }

    public function image(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $variations = min(4, max(1, (int) $request->input('variations', 1)));

        $scene->setWorkState(['image' => AssetStatus::Queued]);
        for ($i = 0; $i < $variations; $i++) {
            // The first regenerated version becomes selected; extra variations are kept for comparison.
            GenerateSceneImageJob::dispatch($project->getKey(), $scene->getKey(), $this->nonce(), $i === 0 && ! $request->boolean('keep_selection'));
        }

        return $this->done($variations > 1 ? $variations.' نسخه تصویر در صف ساخت قرار گرفت.' : 'تصویر صحنه دوباره ساخته می‌شود.');
    }

    public function narration(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $scene->setWorkState(['narration' => AssetStatus::Queued]);
        GenerateSceneNarrationJob::dispatch($project->getKey(), $scene->getKey(), $this->nonce());

        return $this->done('روایت صحنه دوباره ضبط می‌شود.');
    }

    /**
     * Scene preview: (re)mix the audio, then render the scene clip.
     */
    public function preview(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($scene->selectedImage(), 422, 'این صحنه هنوز تصویر ندارد.');

        $scene->setWorkState(['mix' => AssetStatus::Queued, 'video' => AssetStatus::Queued]);
        Bus::chain([
            new MixSceneAudioJob($project->getKey(), $scene->getKey()),
            new RenderSceneJob($project->getKey(), $scene->getKey()),
        ])->dispatch();

        return $this->done('پیش‌نمایش صحنه '.$scene->scene_number.' در حال ساخت است.');
    }

    public function destroy(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $scene->delete();
        $this->renumber($project);
        $this->invalidator->finalChanged($project);

        return $this->done('صحنه حذف شد (فایل‌های ساخته‌شده حفظ می‌شوند).');
    }

    /**
     * Split a scene's narration into two scenes at a sentence boundary.
     */
    public function split(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $text = (string) $scene->narration_original;
        $sentences = preg_split('/(?<=[.!؟?…])\s+/u', trim($text)) ?: [];
        abort_if(count($sentences) < 2, 422, 'روایت این صحنه برای تقسیم کوتاه است.');

        $at = (int) $request->input('sentence', (int) ceil(count($sentences) / 2));
        $at = max(1, min(count($sentences) - 1, $at));
        $first = implode(' ', array_slice($sentences, 0, $at));
        $second = implode(' ', array_slice($sentences, $at));

        DB::transaction(function () use ($project, $scene, $first, $second) {
            $project->scenes()->where('sort_order', '>', $scene->sort_order)->increment('sort_order');

            $copy = $scene->replicate(['work_state', 'approved_at', 'final_image_prompt', 'prompt_edited']);
            $copy->fill([
                'narration_original' => $second,
                'narration_tts' => $this->pronunciation->normalize($second, $project),
                'narration_en' => null,
                'title' => trim(($scene->title ?: 'صحنه').' (ادامه)'),
                'sort_order' => $scene->sort_order + 1,
                'duration_ms' => null,
            ]);
            $copy->forceFill(['work_state' => [], 'status' => AssetStatus::Pending])->save();
            $copy->characters()->sync($scene->characters()->pluck('story_characters.id'));
            if ($scene->continuityState) {
                $project->continuityStates()->create(['scene_id' => $copy->getKey(), 'state' => $scene->continuityState->state, 'source' => 'manual']);
            }

            $scene->forceFill(['narration_original' => $first, 'narration_tts' => $this->pronunciation->normalize($first, $project), 'narration_en' => null])->save();
        });

        $this->renumber($project);
        $this->invalidator->narrationChanged($scene->fresh());

        return $this->done('صحنه '.$scene->scene_number.' به دو صحنه تقسیم شد.');
    }

    public function reorder(Request $request, StoryProject $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $ids = $request->validate(['order' => ['required', 'array'], 'order.*' => ['string']])['order'];
        $owned = $project->scenes()->pluck('id')->all();
        abort_unless(count(array_diff($ids, $owned)) === 0 && count($ids) === count($owned), 422, 'ترتیب نامعتبر است.');

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                StoryScene::whereKey($id)->update(['sort_order' => $index + 1, 'scene_number' => $index + 1]);
            }
        });
        $this->invalidator->finalChanged($project);

        return response()->json(['ok' => true]);
    }

    public function storeDialogue(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $this->validateDialogue($request, $project);
        $scene->dialogues()->create($data + ['sort_order' => (int) $scene->dialogues()->max('sort_order') + 1, 'text_tts' => $this->pronunciation->normalize($data['text_original'], $project)]);
        if (! empty($data['character_id'])) {
            $scene->characters()->syncWithoutDetaching([$data['character_id']]);
        }
        $this->invalidator->sceneOutputsChanged($scene);

        return $this->done('دیالوگ اضافه شد.');
    }

    public function updateDialogue(Request $request, StoryProject $project, StoryDialogue $dialogue): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $dialogue, $dialogue->scene?->project_id);
        $data = $this->validateDialogue($request, $project);

        $dialogue->fill($data);
        $voiceRelevant = $dialogue->isDirty(['text_original', 'character_id', 'emotion', 'energy', 'pace', 'delivery']);
        $mixRelevant = $dialogue->isDirty(['start_offset_ms', 'volume', 'text_en']);
        if ($dialogue->isDirty('text_original')) {
            $dialogue->text_tts = $this->pronunciation->normalize($data['text_original'], $project);
        }
        $dialogue->save();

        if ($voiceRelevant) {
            $this->invalidator->dialogueChanged($dialogue);
        } elseif ($mixRelevant) {
            $this->invalidator->sceneOutputsChanged($dialogue->scene);
        }

        return $this->done($voiceRelevant ? 'دیالوگ ذخیره شد؛ صدای آن نیاز به ضبط مجدد دارد.' : 'دیالوگ ذخیره شد.');
    }

    public function regenerateDialogue(Request $request, StoryProject $project, StoryDialogue $dialogue): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $dialogue, $dialogue->scene?->project_id);
        $dialogue->forceFill(['status' => AssetStatus::Queued])->save();
        GenerateSceneDialogueJob::dispatch($project->getKey(), $dialogue->getKey(), $this->nonce());

        return $this->done('دیالوگ دوباره ضبط می‌شود.');
    }

    public function destroyDialogue(Request $request, StoryProject $project, StoryDialogue $dialogue): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $dialogue, $dialogue->scene?->project_id);
        $scene = $dialogue->scene;
        $dialogue->delete();
        $this->invalidator->sceneOutputsChanged($scene);

        return $this->done('دیالوگ حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDialogue(Request $request, StoryProject $project): array
    {
        $data = $request->validate([
            'character_id' => ['nullable', Rule::exists('story_characters', 'id')->where('project_id', $project->getKey())],
            'text_original' => ['required', 'string', 'max:2000'],
            'text_en' => ['nullable', 'string', 'max:2000'],
            'emotion' => ['nullable', 'string', 'max:64'],
            'energy' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'pace' => ['nullable', Rule::in(['slow', 'normal', 'slightly_fast', 'fast'])],
            'delivery' => ['nullable', 'string', 'max:255'],
            'start_offset_seconds' => ['nullable', 'numeric', 'min:0', 'max:180'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:2'],
        ]);

        $data['start_offset_ms'] = isset($data['start_offset_seconds']) ? (int) round($data['start_offset_seconds'] * 1000) : null;
        $data['volume'] = $data['volume'] ?? 1.0;
        unset($data['start_offset_seconds']);

        return $data;
    }

    private function renumber(StoryProject $project): void
    {
        foreach ($project->scenes()->get() as $index => $scene) {
            if ($scene->scene_number !== $index + 1 || $scene->sort_order !== $index + 1) {
                $scene->forceFill(['scene_number' => $index + 1, 'sort_order' => $index + 1])->save();
            }
        }
    }
}
