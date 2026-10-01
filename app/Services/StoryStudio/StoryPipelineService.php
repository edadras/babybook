<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\ProjectStatus;
use App\Jobs\StoryStudio\AnalyzeStoryJob;
use App\Jobs\StoryStudio\GenerateCharacterReferenceJob;
use App\Jobs\StoryStudio\GenerateCoverArtJob;
use App\Jobs\StoryStudio\GenerateSceneDialogueJob;
use App\Jobs\StoryStudio\GenerateSceneImageJob;
use App\Jobs\StoryStudio\GenerateSceneNarrationJob;
use App\Jobs\StoryStudio\GenerateSoundEffectJob;
use App\Jobs\StoryStudio\GenerateVoiceCandidatesJob;
use App\Jobs\StoryStudio\MixSceneAudioJob;
use App\Jobs\StoryStudio\RenderFinalVideoJob;
use App\Jobs\StoryStudio\RenderSceneJob;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryDialogue;
use App\Models\StoryStudio\StoryPipelineRun;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Models\StoryStudio\StorySoundEffect;
use Illuminate\Support\Collection;

/**
 * The pipeline orchestrator.
 *
 * ANALYZE → CHARACTERS → CHARACTER REFERENCES → VOICE CASTING → SCENES →
 * IMAGES → AUDIO → SFX → MIX → PREVIEW → FINAL RENDER
 *
 * advance() is idempotent: it inspects the database, dispatches jobs only for
 * items that are missing or stale (never for completed work), and stops at
 * approval gates in manual mode. Every job calls back into afterJob().
 */
class StoryPipelineService
{
    private const IN_FLIGHT = [AssetStatus::Queued, AssetStatus::Generating];

    private const DONE = [AssetStatus::Ready, AssetStatus::Approved];

    public function __construct(
        private readonly ImageDirectorService $imageDirector,
        private readonly CostTracker $costs,
    ) {}

    public function activeRun(StoryProject $project): ?StoryPipelineRun
    {
        return $project->pipelineRuns()->whereIn('status', ['running', 'paused', 'waiting'])->latest('created_at')->first();
    }

    /**
     * Start (or continue) producing the story. Paid generation requires the
     * administrator to have confirmed the cost estimate.
     */
    public function start(StoryProject $project, bool $costConfirmed): StoryPipelineRun
    {
        $estimate = $this->costs->estimate($project);

        $project->forceFill([
            'paused_at' => null,
            'cancelled_at' => null,
            'last_error' => null,
            'estimated_cost' => $estimate['total'],
            'cost_confirmed_at' => $costConfirmed ? now() : $project->cost_confirmed_at,
        ])->save();

        $run = $this->activeRun($project) ?? $project->pipelineRuns()->create([
            'mode' => $project->mode,
            'status' => 'running',
            'estimated_cost' => $estimate['total'],
        ]);
        $run->forceFill([
            'status' => 'running',
            'paused_at' => null,
            'started_at' => $run->started_at ?? now(),
            'cost_confirmed_at' => $costConfirmed ? now() : $run->cost_confirmed_at,
        ])->save();

        $this->advance($project->fresh());

        return $run->fresh();
    }

    public function pause(StoryProject $project): void
    {
        $project->forceFill(['paused_at' => now()])->save();
        $this->activeRun($project)?->forceFill(['status' => 'paused', 'paused_at' => now()])->save();
    }

    public function resume(StoryProject $project): void
    {
        $project->forceFill(['paused_at' => null])->save();
        $this->activeRun($project)?->forceFill(['status' => 'running', 'paused_at' => null])->save();
        $this->advance($project->fresh());
    }

    /**
     * Cancel: queued jobs exit without calling providers; completed work is kept.
     */
    public function cancel(StoryProject $project): void
    {
        $project->forceFill(['cancelled_at' => now(), 'paused_at' => null])->save();
        $this->activeRun($project)?->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        $this->resetStates($project, self::IN_FLIGHT);
        $project->refresh()->forceFill(['status' => $this->restingStatus($project)])->save();
    }

    /**
     * Retry everything that failed (or got stuck), keeping completed work.
     */
    public function retry(StoryProject $project): StoryPipelineRun
    {
        $this->resetStates($project, [AssetStatus::Failed], true);

        return $this->start($project->fresh(), $project->cost_confirmed_at !== null);
    }

    public function approve(StoryProject $project, string $gate): void
    {
        $column = match ($gate) {
            'script' => 'script_approved_at',
            'characters' => 'characters_approved_at',
            'voices' => 'voices_approved_at',
            'storyboard' => 'storyboard_approved_at',
            'review' => 'review_approved_at',
            'safety' => 'safety_acknowledged_at',
            default => abort(404),
        };

        if ($gate === 'characters') {
            $project->characters()->where('is_narrator', false)->get()->each(fn (StoryCharacter $c) => $c->character_locked ?: $c->lock());
        }

        if ($gate === 'voices') {
            foreach ($project->characters()->with('voiceProfile')->get() as $character) {
                $character->voiceProfile?->forceFill(['locked' => true])->save();
            }
        }

        $project->forceFill([$column => now()])->save();

        if ($this->activeRun($project)) {
            $this->advance($project->fresh());
        }
    }

    public function afterJob(StoryProject $project): void
    {
        $run = $this->activeRun($project);

        if ($run && $run->status === 'running') {
            $this->advance($project);
        } else {
            $project->forceFill(['progress' => $this->overallProgress($this->report($project))])->save();
        }
    }

    /**
     * Inspect state and dispatch the next work. Safe to call any number of times.
     */
    public function advance(StoryProject $project): void
    {
        $project->refresh();
        $run = $this->activeRun($project);

        if (! $run || $project->paused_at || $project->cancelled_at) {
            return;
        }

        $states = [];
        foreach (PipelineStage::cases() as $stage) {
            $result = $this->runStage($project, $stage);
            $states[$stage->value] = $result;

            if ($result['status'] !== 'done') {
                $this->persistRun($project, $run, $stage, $result, $states);

                // Mark everything queued before dispatching anything, so nested or
                // concurrent advances (sync queue, parallel workers) skip these items.
                $jobs = array_map(fn (callable $queue) => $queue(), $result['queue'] ?? []);
                foreach ($jobs as $job) {
                    dispatch($job);
                }

                return;
            }
        }

        $run->forceFill(['status' => 'completed', 'current_stage' => null, 'stage_states' => $this->report($project), 'finished_at' => now()])->save();
        $project->forceFill(['status' => ProjectStatus::Completed, 'current_stage' => null, 'progress' => 100])->save();
    }

    /**
     * @param  array{status: string, done: int, total: int, message?: string}  $result
     * @param  array<string, array<string, mixed>>  $states
     */
    private function persistRun(StoryProject $project, StoryPipelineRun $run, PipelineStage $stage, array $result, array $states): void
    {
        $report = $this->report($project);

        $status = match (true) {
            $result['status'] === 'failed' => ProjectStatus::Failed,
            $result['status'] === 'waiting' => match ($stage) {
                PipelineStage::Analyze, PipelineStage::Characters, PipelineStage::CharacterReferences, PipelineStage::VoiceCasting => ProjectStatus::CharactersReady,
                PipelineStage::Scenes, PipelineStage::Images => ProjectStatus::StoryboardReady,
                default => ProjectStatus::ReadyForReview,
            },
            default => match ($stage) {
                PipelineStage::Analyze, PipelineStage::Characters => ProjectStatus::Analyzing,
                PipelineStage::CharacterReferences, PipelineStage::Images, PipelineStage::Scenes => ProjectStatus::GeneratingImages,
                PipelineStage::VoiceCasting => ProjectStatus::GeneratingVoices,
                PipelineStage::Audio, PipelineStage::SoundEffects, PipelineStage::Mix => ProjectStatus::GeneratingAudio,
                PipelineStage::Preview, PipelineStage::FinalRender => ProjectStatus::Rendering,
            },
        };

        $run->forceFill([
            'status' => $result['status'] === 'waiting' ? 'waiting' : 'running',
            'current_stage' => $stage,
            'stage_states' => $report,
        ])->save();

        if ($result['status'] === 'waiting') {
            // A waiting run resumes through approve(); keep it discoverable as active.
            $run->forceFill(['status' => 'running'])->save();
        }

        $project->forceFill([
            'status' => $status,
            'current_stage' => $stage,
            'progress' => $this->overallProgress($report),
            'last_error' => $result['status'] === 'failed' ? ($project->last_error ?: ($result['message'] ?? null)) : $project->last_error,
        ])->save();
    }

    /**
     * @return array{status: 'done'|'working'|'waiting'|'failed', done: int, total: int, message?: string}
     */
    private function runStage(StoryProject $project, PipelineStage $stage): array
    {
        $manual = $project->mode === GenerationMode::Manual;

        switch ($stage) {
            case PipelineStage::Analyze:
                $hasScript = $project->scenes()->exists() && $project->story_metadata;
                if (! $hasScript) {
                    return $this->items($project, [[
                        'state' => $project->workState('analysis'),
                        'dispatch' => function () use ($project) {
                            $project->setWorkState('analysis', AssetStatus::Queued);

                            return new AnalyzeStoryJob($project->getKey(), null, 'auto:'.md5($project->original_text));
                        },
                    ]]);
                }
                if (data_get($project->safety_report, 'requires_acknowledgement') && ! $project->safety_acknowledged_at) {
                    return ['status' => 'waiting', 'done' => 1, 'total' => 1, 'message' => 'safety'];
                }
                if ($manual && ! $project->script_approved_at) {
                    return ['status' => 'waiting', 'done' => 1, 'total' => 1, 'message' => 'script'];
                }

                return ['status' => 'done', 'done' => 1, 'total' => 1];

            case PipelineStage::Characters:
                return ['status' => 'done', 'done' => 1, 'total' => 1];

            case PipelineStage::CharacterReferences:
                $characters = $project->characters()->where('is_major', true)->where('is_narrator', false)->get();
                $result = $this->items($project, $characters->map(fn (StoryCharacter $character) => [
                    'state' => $character->status,
                    'dispatch' => function () use ($project, $character) {
                        $character->forceFill(['status' => AssetStatus::Queued])->save();

                        return new GenerateCharacterReferenceJob($project->getKey(), $character->getKey(), 'auto');
                    },
                ])->all());
                if ($result['status'] !== 'done') {
                    return $result;
                }
                if ($manual && ! $project->characters_approved_at) {
                    return ['status' => 'waiting'] + $result;
                }
                if (! $manual) {
                    $characters->each(fn (StoryCharacter $c) => $c->character_locked ?: $c->lock());
                }

                return $result;

            case PipelineStage::VoiceCasting:
                $speakers = $this->speakingCharacters($project);
                $items = [];
                foreach ($speakers as $character) {
                    if ($character->voice_profile_id) {
                        $items[] = ['state' => AssetStatus::Approved, 'dispatch' => null];

                        continue;
                    }
                    if ($manual && $character->voice_status === AssetStatus::Ready) {
                        // Candidates exist; waiting for the administrator to pick one.
                        $items[] = ['state' => AssetStatus::Ready, 'dispatch' => null, 'awaiting' => true];

                        continue;
                    }
                    $items[] = [
                        'state' => $character->voice_status === AssetStatus::Ready ? AssetStatus::Pending : $character->voice_status,
                        'dispatch' => function () use ($project, $character, $manual) {
                            $character->forceFill(['voice_status' => AssetStatus::Queued])->save();

                            return new GenerateVoiceCandidatesJob($project->getKey(), $character->getKey(), 'auto', ! $manual);
                        },
                    ];
                }
                $result = $this->items($project, $items);
                $awaiting = collect($items)->contains(fn ($item) => $item['awaiting'] ?? false);
                if ($result['status'] === 'done' && ($awaiting || ($manual && ! $project->voices_approved_at))) {
                    return ['status' => 'waiting'] + $result;
                }

                return $result;

            case PipelineStage::Scenes:
                // Compose prompts for the storyboard; image jobs refresh them again right before generating.
                foreach ($project->scenes()->whereNull('final_image_prompt')->get() as $scene) {
                    $this->imageDirector->prepareScene($scene);
                }
                $count = $project->scenes()->count();
                if ($manual && ! $project->storyboard_approved_at) {
                    return ['status' => 'waiting', 'done' => $count, 'total' => $count, 'message' => 'storyboard'];
                }
                if (! $project->cost_confirmed_at) {
                    return ['status' => 'waiting', 'done' => $count, 'total' => $count, 'message' => 'cost'];
                }

                return ['status' => 'done', 'done' => $count, 'total' => $count];

            case PipelineStage::Images:
                $items = $project->scenes->map(fn (StoryScene $scene) => $this->sceneItem($project, $scene, 'image', GenerateSceneImageJob::class))->all();
                $items[] = [
                    'state' => $project->workState('cover_art'),
                    'dispatch' => function () use ($project) {
                        $project->setWorkState('cover_art', AssetStatus::Queued);

                        return new GenerateCoverArtJob($project->getKey(), null, 'auto');
                    },
                ];

                return $this->items($project, $items);

            case PipelineStage::Audio:
                $items = [];
                foreach ($project->scenes()->with('dialogues')->get() as $scene) {
                    if (trim((string) $scene->narration_original) !== '') {
                        $items[] = $this->sceneItem($project, $scene, 'narration', GenerateSceneNarrationJob::class);
                    }
                    foreach ($scene->dialogues as $dialogue) {
                        $items[] = [
                            'state' => $dialogue->status,
                            'dispatch' => function () use ($project, $dialogue) {
                                $dialogue->forceFill(['status' => AssetStatus::Queued])->save();

                                return new GenerateSceneDialogueJob($project->getKey(), $dialogue->getKey(), 'auto');
                            },
                        ];
                    }
                }

                return $this->items($project, $items);

            case PipelineStage::SoundEffects:
                $effects = StorySoundEffect::query()
                    ->whereIn('scene_id', $project->scenes()->pluck('id'))
                    ->where('enabled', true)
                    ->get();

                return $this->items($project, $effects->map(fn (StorySoundEffect $effect) => [
                    'state' => $effect->status,
                    'dispatch' => function () use ($project, $effect) {
                        $effect->forceFill(['status' => AssetStatus::Queued])->save();

                        return new GenerateSoundEffectJob($project->getKey(), $effect->getKey(), 'auto');
                    },
                ])->all());

            case PipelineStage::Mix:
                return $this->items($project, $project->scenes->map(fn (StoryScene $scene) => $this->sceneItem($project, $scene, 'mix', MixSceneAudioJob::class))->all());

            case PipelineStage::Preview:
                $result = $this->items($project, $project->scenes->map(fn (StoryScene $scene) => $this->sceneItem($project, $scene, 'video', RenderSceneJob::class))->all());
                if ($result['status'] === 'done' && $manual && ! $project->review_approved_at) {
                    return ['status' => 'waiting', 'message' => 'review'] + $result;
                }

                return $result;

            case PipelineStage::FinalRender:
                return $this->items($project, [[
                    'state' => $project->workState('final'),
                    'dispatch' => function () use ($project) {
                        $project->setWorkState('final', AssetStatus::Queued);

                        return new RenderFinalVideoJob($project->getKey(), null, 'auto');
                    },
                ]]);
        }

        return ['status' => 'done', 'done' => 0, 'total' => 0];
    }

    /**
     * @param  class-string  $jobClass
     * @return array{state: AssetStatus, dispatch: callable}
     */
    private function sceneItem(StoryProject $project, StoryScene $scene, string $key, string $jobClass): array
    {
        return [
            'state' => $scene->workState($key),
            'dispatch' => function () use ($project, $scene, $key, $jobClass) {
                $scene->setWorkState([$key => AssetStatus::Queued]);

                return new $jobClass($project->getKey(), $scene->getKey(), 'auto');
            },
        ];
    }

    /**
     * Evaluate a set of work items. Pending/stale items are returned in 'queue'
     * as callables that mark the item queued and return its job.
     *
     * @param  list<array{state: AssetStatus, dispatch: ?callable}>  $items
     * @return array{status: 'done'|'working'|'failed', done: int, total: int, queue?: list<callable>}
     */
    private function items(StoryProject $project, array $items): array
    {
        $total = count($items);
        $done = count(array_filter($items, fn ($item) => in_array($item['state'], self::DONE, true)));
        $failed = count(array_filter($items, fn ($item) => $item['state'] === AssetStatus::Failed));
        $toDispatch = array_values(array_filter($items, fn ($item) => $item['dispatch'] && in_array($item['state'], [AssetStatus::Pending, AssetStatus::Stale], true)));
        $inFlight = count(array_filter($items, fn ($item) => in_array($item['state'], self::IN_FLIGHT, true)));

        if ($done === $total) {
            return ['status' => 'done', 'done' => $done, 'total' => $total];
        }

        if ($failed > 0 && $toDispatch === [] && $inFlight === 0) {
            return ['status' => 'failed', 'done' => $done, 'total' => $total];
        }

        return ['status' => 'working', 'done' => $done, 'total' => $total, 'queue' => array_column($toDispatch, 'dispatch')];
    }

    /**
     * @return Collection<int, StoryCharacter>
     */
    public function speakingCharacters(StoryProject $project)
    {
        $speakerIds = StoryDialogue::query()
            ->whereIn('scene_id', $project->scenes()->pluck('id'))
            ->whereNotNull('character_id')
            ->distinct()
            ->pluck('character_id');

        return $project->characters()
            ->where(fn ($query) => $query->whereIn('id', $speakerIds)->orWhere('is_narrator', true))
            ->get();
    }

    /**
     * Read-only progress snapshot per stage, computed from the database.
     *
     * @return array<string, array{status: string, done: int, total: int}>
     */
    public function report(StoryProject $project): array
    {
        $project->loadMissing('scenes');
        $scenes = $project->scenes()->with(['dialogues', 'soundEffects'])->get();
        $count = fn (iterable $states) => [
            'done' => collect($states)->filter(fn ($s) => in_array($s, self::DONE, true))->count(),
            'total' => collect($states)->count(),
            'failed' => collect($states)->contains(AssetStatus::Failed),
            'working' => collect($states)->contains(fn ($s) => in_array($s, self::IN_FLIGHT, true)),
        ];
        $status = function (array $c, bool $gateOpen = true) {
            return match (true) {
                $c['total'] > 0 && $c['done'] === $c['total'] && $gateOpen => 'done',
                $c['total'] > 0 && $c['done'] === $c['total'] => 'awaiting_approval',
                $c['failed'] => 'failed',
                $c['working'] || $c['done'] > 0 => 'running',
                default => 'pending',
            };
        };
        $manual = $project->mode === GenerationMode::Manual;

        $analysis = $count([$scenes->isNotEmpty() ? AssetStatus::Ready : $project->workState('analysis')]);
        $characters = $project->characters()->where('is_major', true)->where('is_narrator', false)->get();
        $refs = $count($characters->pluck('status'));
        $voices = $count($this->speakingCharacters($project)->map(fn ($c) => $c->voice_profile_id ? AssetStatus::Approved : $c->voice_status));
        $storyboard = $count($scenes->map(fn ($s) => $s->final_image_prompt ? AssetStatus::Ready : AssetStatus::Pending));
        $images = $count($scenes->map(fn ($s) => $s->workState('image')));
        $audioStates = [];
        foreach ($scenes as $scene) {
            if (trim((string) $scene->narration_original) !== '') {
                $audioStates[] = $scene->workState('narration');
            }
            foreach ($scene->dialogues as $dialogue) {
                $audioStates[] = $dialogue->status;
            }
        }
        $audio = $count($audioStates);
        $sfx = $count($scenes->flatMap(fn ($s) => $s->soundEffects->where('enabled', true)->pluck('status')));
        $mix = $count($scenes->map(fn ($s) => $s->workState('mix')));
        $video = $count($scenes->map(fn ($s) => $s->workState('video')));
        $final = $count([$project->workState('final')]);

        $row = fn (array $c, string $s) => ['status' => $s, 'done' => $c['done'], 'total' => $c['total']];

        return [
            PipelineStage::Analyze->value => $row($analysis, $status($analysis, ! $manual || (bool) $project->script_approved_at)),
            PipelineStage::Characters->value => $row($count($characters->isNotEmpty() || $scenes->isNotEmpty() ? [AssetStatus::Ready] : [AssetStatus::Pending]), $scenes->isNotEmpty() ? 'done' : 'pending'),
            PipelineStage::CharacterReferences->value => $row($refs, $refs['total'] === 0 && $scenes->isNotEmpty() ? 'done' : $status($refs, ! $manual || (bool) $project->characters_approved_at)),
            PipelineStage::VoiceCasting->value => $row($voices, $status($voices, ! $manual || (bool) $project->voices_approved_at)),
            PipelineStage::Scenes->value => $row($storyboard, $status($storyboard, ! $manual || (bool) $project->storyboard_approved_at)),
            PipelineStage::Images->value => $row($images, $status($images)),
            PipelineStage::Audio->value => $row($audio, $audio['total'] === 0 && $scenes->isNotEmpty() ? 'done' : $status($audio)),
            PipelineStage::SoundEffects->value => $row($sfx, $sfx['total'] === 0 && $mix['done'] > 0 ? 'done' : $status($sfx)),
            PipelineStage::Mix->value => $row($mix, $status($mix)),
            PipelineStage::Preview->value => $row($video, $status($video, ! $manual || (bool) $project->review_approved_at)),
            PipelineStage::FinalRender->value => $row($final, $status($final)),
        ];
    }

    /**
     * Which approval the administrator needs to give next, if any.
     *
     * @param  array<string, array{status: string, done: int, total: int}>  $report
     */
    public function pendingGate(StoryProject $project, array $report): ?string
    {
        if (! $project->scenes()->exists()) {
            return null;
        }
        if (data_get($project->safety_report, 'requires_acknowledgement') && ! $project->safety_acknowledged_at) {
            return 'safety';
        }
        if ($project->mode !== GenerationMode::Manual) {
            return null;
        }
        if (! $project->script_approved_at) {
            return 'script';
        }

        foreach (['character_references' => 'characters', 'voice_casting' => 'voices', 'scenes' => 'storyboard', 'preview' => 'review'] as $stage => $gate) {
            if (($report[$stage]['status'] ?? null) === 'awaiting_approval') {
                return $gate;
            }
            if (($report[$stage]['status'] ?? null) !== 'done') {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{status: string, done: int, total: int}>  $report
     */
    public function overallProgress(array $report): int
    {
        $sum = 0.0;
        $weights = 0;
        foreach (PipelineStage::cases() as $stage) {
            $row = $report[$stage->value] ?? ['status' => 'pending', 'done' => 0, 'total' => 0];
            $fraction = match (true) {
                in_array($row['status'], ['done', 'awaiting_approval'], true) => 1.0,
                $row['total'] > 0 => $row['done'] / $row['total'],
                default => 0.0,
            };
            $sum += $fraction * $stage->weight();
            $weights += $stage->weight();
        }

        return (int) min(100, round($sum / max(1, $weights) * 100));
    }

    /**
     * @param  list<AssetStatus>  $from
     */
    private function resetStates(StoryProject $project, array $from, bool $includeStuck = false): void
    {
        $values = array_map(fn (AssetStatus $s) => $s->value, $from);
        $stuck = $includeStuck ? array_map(fn (AssetStatus $s) => $s->value, self::IN_FLIGHT) : [];

        foreach ($project->scenes as $scene) {
            $state = $scene->work_state ?? [];
            $changed = false;
            foreach ($state as $key => $value) {
                if (in_array($value, $values, true) || (in_array($value, $stuck, true) && $scene->updated_at?->lt(now()->subMinutes(GenerationGuard::STALE_AFTER_MINUTES)))) {
                    $state[$key] = AssetStatus::Pending->value;
                    $changed = true;
                }
            }
            if ($changed) {
                $scene->forceFill(['work_state' => $state])->save();
            }
            $scene->dialogues()->whereIn('status', $values)->update(['status' => AssetStatus::Pending->value]);
            $scene->soundEffects()->whereIn('status', $values)->update(['status' => AssetStatus::Pending->value]);
        }

        $project->characters()->whereIn('status', $values)->update(['status' => AssetStatus::Pending->value]);
        $project->characters()->whereIn('voice_status', $values)->update(['voice_status' => AssetStatus::Pending->value]);

        $state = $project->fresh()->work_state ?? [];
        foreach ($state as $key => $value) {
            if (in_array($value, $values, true)) {
                $state[$key] = AssetStatus::Pending->value;
            }
        }
        $project->forceFill(['work_state' => $state, 'last_error' => null])->save();
    }

    private function restingStatus(StoryProject $project): ProjectStatus
    {
        if ($project->workState('final') === AssetStatus::Ready) {
            return ProjectStatus::Completed;
        }

        return $project->scenes()->exists() ? ProjectStatus::StoryboardReady : ProjectStatus::Draft;
    }
}
