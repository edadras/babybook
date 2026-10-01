<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetType;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\CostTracker;
use App\Services\StoryStudio\StoryPipelineService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Render (رندر), final outputs (خروجی) and the cost dashboard (هزینه‌ها).
 */
class OutputController extends StudioController
{
    public function render(Request $request, StoryProject $project, StoryPipelineService $pipeline, CostTracker $costs): View
    {
        $this->authorizeProject($request, $project, 'view');

        return view('story-studio.workspace.render', [
            'project' => $project,
            'report' => $pipeline->report($project),
            'estimate' => $costs->estimate($project),
            'scenes' => $project->scenes()->with(['assets' => fn ($q) => $q->where('type', AssetType::SceneVideo)->where('selected', true)])->get(),
            'renders' => $project->renderJobs()->latest()->limit(10)->get(),
        ]);
    }

    public function export(Request $request, StoryProject $project, CostTracker $costs): View
    {
        $this->authorizeProject($request, $project, 'view');
        $outputs = [];
        foreach ([AssetType::FinalVideo, AssetType::FinalAudio, AssetType::SubtitleSrt, AssetType::SubtitleVtt, AssetType::Cover, AssetType::AssetsZip] as $type) {
            $outputs[$type->value] = $project->latestAsset($type);
        }

        return view('story-studio.workspace.export', [
            'project' => $project->loadCount(['scenes', 'characters']),
            'outputs' => $outputs,
            'costs' => $costs->summary($project),
        ]);
    }

    public function costs(Request $request, StoryProject $project, CostTracker $costs): View
    {
        $this->authorizeProject($request, $project, 'view');

        return view('story-studio.workspace.costs', [
            'project' => $project,
            'summary' => $costs->summary($project),
            'estimate' => $costs->estimate($project),
            'jobs' => $project->generationJobs()->latest()->limit(50)->get(),
        ]);
    }
}
