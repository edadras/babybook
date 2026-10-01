<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AspectRatio;
use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\NarrationStyle;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\Resolution;
use App\Enums\StoryStudio\StoryCategory;
use App\Enums\StoryStudio\TargetAge;
use App\Enums\StoryStudio\VisualStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoryStudio\StoryProjectRequest;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\ProjectProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StoryProjectController extends Controller
{
    public function index(Request $request): View
    {
        $status = ProjectStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $projects = StoryProject::query()
            ->visibleTo($request->user())
            ->with('cover')
            ->withCount(['scenes', 'characters'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('story-studio.projects.index', [
            'projects' => $projects,
            'statusFilter' => $status,
            'search' => $search,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('story-studio.projects.create', [
            'project' => new StoryProject([
                'language' => 'fa',
                'target_age' => TargetAge::EarlyReader,
                'category' => StoryCategory::Bedtime,
                'visual_style' => VisualStyle::ChildrensBook,
                'narration_style' => NarrationStyle::Warm,
                'aspect_ratio' => AspectRatio::Landscape,
                'resolution' => Resolution::FullHD,
                'mode' => GenerationMode::Manual,
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoryProjectRequest $request): RedirectResponse
    {
        $project = new StoryProject($request->validated());
        $project->user_id = $request->user()?->getKey();
        $project->save();

        return redirect()
            ->route('story-studio.projects.show', $project)
            ->with('status', 'پروژه «'.$project->title.'» ساخته شد.');
    }

    public function show(Request $request, StoryProject $project, ProjectProgressService $progress): View
    {
        Gate::forUser($request->user())->authorize('view', $project);

        $project->load(['settings', 'cover'])->loadCount(['scenes', 'characters', 'locations']);

        return view('story-studio.projects.show', [
            'project' => $project,
            'progress' => $progress->summarize($project),
        ]);
    }

    public function edit(Request $request, StoryProject $project): View
    {
        Gate::forUser($request->user())->authorize('update', $project);

        return view('story-studio.projects.edit', [
            'project' => $project,
            ...$this->formOptions(),
        ]);
    }

    public function update(StoryProjectRequest $request, StoryProject $project): RedirectResponse
    {
        abort_unless($project->isEditable(), 409, 'پروژه در حال پردازش است و فعلاً قابل ویرایش نیست.');

        $project->fill($request->validated());
        $textChanged = $project->isDirty('original_text');
        $project->save();

        // Later phases: a changed story text marks the analysis (and everything derived) stale.
        $message = $textChanged
            ? 'متن داستان تغییر کرد؛ برای به‌روزرسانی صحنه‌ها تحلیل را دوباره اجرا کنید.'
            : 'تغییرات ذخیره شد.';

        return redirect()->route('story-studio.projects.show', $project)->with('status', $message);
    }

    public function destroy(Request $request, StoryProject $project): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('delete', $project);

        $title = $project->title;
        $project->delete();

        return redirect()->route('story-studio.index')->with('status', 'پروژه «'.$title.'» به سطل بازیافت منتقل شد.');
    }

    public function progress(Request $request, StoryProject $project, ProjectProgressService $progress): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $project);

        return response()->json($progress->summarize($project));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'targetAges' => TargetAge::cases(),
            'categories' => StoryCategory::cases(),
            'visualStyles' => VisualStyle::cases(),
            'narrationStyles' => NarrationStyle::cases(),
            'aspectRatios' => AspectRatio::cases(),
            'resolutions' => Resolution::cases(),
            'modes' => GenerationMode::cases(),
            'stages' => PipelineStage::cases(),
        ];
    }
}
