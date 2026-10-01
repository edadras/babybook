<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\PronunciationType;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryPronunciation;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\PronunciationDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Persian pronunciation dictionary (واژه / تلفظ صحیح / نوع), global or per project.
 */
class PronunciationController extends StudioController
{
    public function __construct(
        private readonly PronunciationDictionaryService $dictionary,
        private readonly DependencyInvalidator $invalidator,
    ) {}

    public function index(Request $request, StoryProject $project): View
    {
        $this->authorizeProject($request, $project, 'view');
        $sample = $request->query('sample', mb_substr($project->original_text, 0, 400));

        return view('story-studio.workspace.pronunciation', [
            'project' => $project,
            'entries' => StoryPronunciation::query()->applicableTo($project)->orderBy('word')->get(),
            'types' => PronunciationType::cases(),
            'sample' => $sample,
            'normalized' => $this->dictionary->normalize($sample, $project),
        ]);
    }

    public function store(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate([
            'word' => ['required', 'string', 'max:191'],
            'pronunciation' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PronunciationType::class)],
            'scope' => ['required', Rule::in(['project', 'global'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        StoryPronunciation::updateOrCreate(
            ['project_id' => $data['scope'] === 'project' ? $project->getKey() : null, 'word' => trim($data['word'])],
            ['pronunciation' => trim($data['pronunciation']), 'type' => $data['type'], 'notes' => $data['notes'] ?? null, 'whole_word' => ! $request->boolean('partial')],
        );

        $changed = $this->reapply($project);

        return $this->done('تلفظ «'.$data['word'].'» ذخیره شد.'.($changed ? " {$changed} مورد صدا برای ضبط مجدد علامت خورد." : ''));
    }

    public function destroy(Request $request, StoryProject $project, StoryPronunciation $pronunciation): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($pronunciation->project_id === null || $pronunciation->project_id === $project->getKey(), 404);
        $pronunciation->delete();
        $changed = $this->reapply($project);

        return $this->done('تلفظ حذف شد.'.($changed ? " {$changed} مورد صدا برای ضبط مجدد علامت خورد." : ''));
    }

    /**
     * Recompute TTS texts; only lines whose spoken text changed become stale.
     */
    private function reapply(StoryProject $project): int
    {
        $this->dictionary->flush();
        $changed = 0;

        foreach ($project->scenes()->with('dialogues')->get() as $scene) {
            $tts = $this->dictionary->normalize((string) $scene->narration_original, $project);
            if ($tts !== $scene->narration_tts) {
                $scene->forceFill(['narration_tts' => $tts])->save();
                $this->invalidator->narrationChanged($scene);
                $changed++;
            }
            foreach ($scene->dialogues as $dialogue) {
                $tts = $this->dictionary->normalize($dialogue->text_original, $project);
                if ($tts !== $dialogue->text_tts) {
                    $dialogue->forceFill(['text_tts' => $tts])->save();
                    $this->invalidator->dialogueChanged($dialogue);
                    $changed++;
                }
            }
        }

        return $changed;
    }
}
