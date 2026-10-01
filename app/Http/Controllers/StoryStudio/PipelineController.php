<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Jobs\StoryStudio\AnalyzeStoryJob;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\CostTracker;
use App\Services\StoryStudio\StoryPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PipelineController extends StudioController
{
    public function __construct(private readonly StoryPipelineService $pipeline) {}

    public function start(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $request->validate(['confirm_cost' => ['accepted']], ['confirm_cost.accepted' => 'لطفاً هزینه تخمینی را تأیید کنید.']);

        $this->pipeline->start($project, costConfirmed: true);

        return $this->done('ساخت کتاب آغاز شد. پیشرفت را در پنل وضعیت ببینید.');
    }

    /**
     * Re-run only the AI analysis (manual "analyze again").
     */
    public function analyze(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->setWorkState('analysis', AssetStatus::Queued);
        AnalyzeStoryJob::dispatch($project->getKey(), null, $this->nonce());

        return $this->done('تحلیل دوباره داستان در صف قرار گرفت.');
    }

    public function pause(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->pipeline->pause($project);

        return $this->done('پروژه متوقف شد. کارهای در حال اجرا تمام می‌شوند و کار جدیدی شروع نمی‌شود.');
    }

    public function resume(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->pipeline->resume($project);

        return $this->done('ادامه ساخت.');
    }

    public function cancel(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->pipeline->cancel($project);

        return $this->done('ساخت لغو شد. همه کارهای تکمیل‌شده حفظ شده‌اند.');
    }

    public function retry(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->pipeline->retry($project);

        return $this->done('موارد ناموفق دوباره در صف قرار گرفتند.');
    }

    public function approve(Request $request, StoryProject $project, string $gate): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->pipeline->approve($project, $gate);

        return $this->done(match ($gate) {
            'script' => 'فیلمنامه تأیید شد.',
            'characters' => 'شخصیت‌ها تأیید و قفل شدند.',
            'voices' => 'صداها تأیید و قفل شدند.',
            'storyboard' => 'استوری‌بورد تأیید شد.',
            'review' => 'بازبینی تأیید شد؛ رندر نهایی آغاز می‌شود.',
            'safety' => 'هشدارهای محتوایی بررسی و تأیید شد.',
            default => 'تأیید شد.',
        });
    }

    public function estimate(Request $request, StoryProject $project, CostTracker $costs): JsonResponse
    {
        $this->authorizeProject($request, $project, 'view');

        return response()->json($costs->estimate($project));
    }
}
