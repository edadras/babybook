<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Jobs\StoryStudio\GenerateVoiceCandidatesJob;
use App\Jobs\StoryStudio\GenerateVoicePreviewJob;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryVoiceProfile;
use App\Services\StoryStudio\Contracts\SpeechProviderInterface;
use App\Services\StoryStudio\StoryPipelineService;
use App\Services\StoryStudio\VoiceCastingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class VoiceController extends StudioController
{
    public function index(Request $request, StoryProject $project, StoryPipelineService $pipeline, SpeechProviderInterface $speech): View
    {
        $this->authorizeProject($request, $project, 'view');
        $speakerIds = $pipeline->speakingCharacters($project)->pluck('id');

        $library = [];
        $libraryError = null;
        try {
            $library = Cache::remember('story-studio:voices:'.$speech->name(), 600, fn () => $speech->listVoices());
        } catch (Throwable $exception) {
            $libraryError = $exception->getMessage();
        }

        return view('story-studio.workspace.voices', [
            'project' => $project,
            'characters' => $project->characters()
                ->with(['voiceProfile.preview', 'voiceCandidates.preview', 'referenceAssets'])
                ->withCount('dialogues')
                ->get()
                ->sortByDesc(fn ($c) => [$c->is_narrator, $speakerIds->contains($c->id)]),
            'speakerIds' => $speakerIds,
            'library' => $library,
            'libraryError' => $libraryError,
        ]);
    }

    public function design(Request $request, StoryProject $project, StoryCharacter $character): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_if($character->voiceProfile?->locked, 409, 'صدای این شخصیت قفل است.');

        if ($request->filled('voice_description')) {
            $character->forceFill(['voice_description' => $request->string('voice_description')->limit(2000)->toString()])->save();
        }
        $character->forceFill(['voice_status' => AssetStatus::Queued])->save();
        GenerateVoiceCandidatesJob::dispatch($project->getKey(), $character->getKey(), $this->nonce());

        return $this->done('ساخت صداهای پیشنهادی برای «'.$character->name.'» در صف قرار گرفت.');
    }

    public function select(Request $request, StoryProject $project, StoryVoiceProfile $profile, VoiceCastingService $casting, StoryPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $profile);
        $casting->select($profile);
        $pipeline->afterJob($project->fresh());

        return $this->done('صدای «'.$profile->name.'» برای '.$profile->character->name.' انتخاب شد.');
    }

    public function library(Request $request, StoryProject $project, StoryCharacter $character, VoiceCastingService $casting, StoryPipelineService $pipeline): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate(['voice_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_\-]+$/'], 'name' => ['nullable', 'string', 'max:120']]);
        $casting->assignLibraryVoice($character, $data['voice_id'], $data['name'] ?? null);
        $pipeline->afterJob($project->fresh());

        return $this->done('صدا به «'.$character->name.'» اختصاص یافت.');
    }

    public function preview(Request $request, StoryProject $project, StoryVoiceProfile $profile): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $profile);
        GenerateVoicePreviewJob::dispatch($project->getKey(), $profile->getKey(), $this->nonce());

        return $this->done('نمونه صدا در حال ساخت است.');
    }

    public function lock(Request $request, StoryProject $project, StoryCharacter $character, VoiceCastingService $casting): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $casting->lock($character, ! $request->boolean('unlock'));

        return $this->done($request->boolean('unlock') ? 'قفل صدا باز شد.' : 'صدا قفل شد و در کل پروژه ثابت می‌ماند.');
    }
}
