<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AudioAssetType;
use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\SoundKind;
use App\Enums\StoryStudio\SubtitleMode;
use App\Enums\StoryStudio\Transition;
use App\Jobs\StoryStudio\GenerateSoundEffectJob;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryScene;
use App\Models\StoryStudio\StorySoundEffect;
use App\Services\StoryStudio\AssetStorageService;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\Schema\StorySchemas;
use App\Services\StoryStudio\Support\Ffmpeg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * صداگذاری: sound effects, ambience, music library and mix settings.
 */
class SoundController extends StudioController
{
    public function __construct(private readonly DependencyInvalidator $invalidator) {}

    public function index(Request $request, StoryProject $project): View
    {
        $this->authorizeProject($request, $project, 'view');

        return view('story-studio.workspace.sound', [
            'project' => $project->load('settings'),
            'scenes' => $project->scenes()->with(['soundEffects.audioAssets' => fn ($q) => $q->where('selected', true), 'musicTrack'])->get(),
            'music' => $project->audioAssets()->where('type', AudioAssetType::Music)->latest()->get(),
            'moods' => StorySchemas::MUSIC_MOODS,
        ]);
    }

    public function updateEffect(Request $request, StoryProject $project, StorySoundEffect $effect): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $effect, $effect->scene?->project_id);
        $data = $this->validateEffect($request);

        $effect->fill($data);
        $audioChanged = $effect->isDirty(['prompt', 'kind', 'duration_ms']);
        $effect->save();
        $this->invalidator->soundEffectChanged($effect, $audioChanged);

        return $this->done($audioChanged ? 'افکت ذخیره شد و دوباره ساخته خواهد شد.' : 'افکت ذخیره شد.');
    }

    public function storeEffect(Request $request, StoryProject $project, StoryScene $scene): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $effect = $scene->soundEffects()->create($this->validateEffect($request) + ['sort_order' => (int) $scene->soundEffects()->max('sort_order') + 1]);
        $this->invalidator->soundEffectChanged($effect, false);

        return $this->done('افکت «'.$effect->description.'» اضافه شد.');
    }

    public function regenerateEffect(Request $request, StoryProject $project, StorySoundEffect $effect): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $effect, $effect->scene?->project_id);
        $effect->forceFill(['status' => AssetStatus::Queued])->save();
        GenerateSoundEffectJob::dispatch($project->getKey(), $effect->getKey(), $this->nonce());

        return $this->done('افکت دوباره ساخته می‌شود.');
    }

    public function destroyEffect(Request $request, StoryProject $project, StorySoundEffect $effect): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $effect, $effect->scene?->project_id);
        $scene = $effect->scene;
        $effect->delete();
        $this->invalidator->sceneOutputsChanged($scene);

        return $this->done('افکت حذف شد.');
    }

    /**
     * Upload a royalty-free music track to the project library.
     */
    public function uploadMusic(Request $request, StoryProject $project, AssetStorageService $storage, Ffmpeg $ffmpeg): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate([
            'music' => ['required', 'file', 'max:30720', 'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/aac'],
            'name' => ['nullable', 'string', 'max:120'],
            'mood' => ['nullable', Rule::in(StorySchemas::MUSIC_MOODS)],
            'license' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['music'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $asset = $storage->storeAudio($project, AudioAssetType::Music, 'music:'.Str::uuid(), Str::slug($data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), '_') ?: 'music',
            (string) file_get_contents($file->getRealPath()), $extension, [
                'provider' => 'upload',
                'text' => $data['name'] ?? $file->getClientOriginalName(),
                'metadata' => ['mood' => $data['mood'] ?? null, 'license' => $data['license'] ?? null, 'original_name' => $file->getClientOriginalName()],
            ]);
        $asset->forceFill(['duration_ms' => $ffmpeg->durationMs($storage->localPath($asset))])->save();

        if (! $project->settings->default_music_audio_id) {
            $project->settings->update(['default_music_audio_id' => $asset->getKey()]);
            $project->scenes->each(fn (StoryScene $scene) => $this->invalidator->sceneOutputsChanged($scene));
        }

        return $this->done('موسیقی «'.$asset->text.'» به کتابخانه پروژه اضافه شد.');
    }

    public function assignMusic(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate([
            'scene_id' => ['nullable', Rule::exists('story_scenes', 'id')->where('project_id', $project->getKey())],
            'music_audio_id' => ['nullable', Rule::exists('story_audio_assets', 'id')->where('project_id', $project->getKey())->where('type', AudioAssetType::Music->value)],
            'music_volume' => ['nullable', 'numeric', 'min:0', 'max:1.5'],
            'music_fade_in_seconds' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'music_fade_out_seconds' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        if (! empty($data['scene_id'])) {
            $scene = StoryScene::findOrFail($data['scene_id']);
            $scene->forceFill([
                'music_audio_id' => $data['music_audio_id'] ?? null,
                'music_volume' => $data['music_volume'] ?? null,
                'music_fade_in_ms' => (int) round(($data['music_fade_in_seconds'] ?? 0) * 1000),
                'music_fade_out_ms' => (int) round(($data['music_fade_out_seconds'] ?? 0) * 1000),
            ])->save();
            $this->invalidator->sceneOutputsChanged($scene);

            return $this->done('موسیقی صحنه '.$scene->scene_number.' تنظیم شد.');
        }

        $project->settings->update(['default_music_audio_id' => $data['music_audio_id'] ?? null]);
        $project->scenes->each(fn (StoryScene $scene) => $this->invalidator->sceneOutputsChanged($scene));

        return $this->done('موسیقی پیش‌فرض پروژه تنظیم شد.');
    }

    public function updateSettings(Request $request, StoryProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $request->validate([
            'music_volume' => ['required', 'numeric', 'min:0', 'max:1.5'],
            'ducking_db' => ['required', 'integer', 'min:0', 'max:30'],
            'default_transition' => ['required', Rule::enum(Transition::class)],
            'transition_duration_ms' => ['required', 'integer', 'min:0', 'max:3000'],
            'default_motion' => ['required', Rule::enum(ImageMotion::class)],
            'subtitle_mode' => ['required', Rule::enum(SubtitleMode::class)],
            'subtitle_font_size' => ['required', 'integer', 'min:24', 'max:96'],
        ]);
        $data['burn_subtitles'] = $request->boolean('burn_subtitles');
        $data['show_title_on_cover'] = $request->boolean('show_title_on_cover');

        $settings = $project->settings;
        $settings->fill($data);
        $mixChanged = $settings->isDirty(['music_volume', 'ducking_db']);
        $settings->save();

        if ($mixChanged) {
            $project->scenes->each(fn (StoryScene $scene) => $this->invalidator->sceneOutputsChanged($scene));
        } else {
            $this->invalidator->finalChanged($project);
        }

        return $this->done('تنظیمات صدا و خروجی ذخیره شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEffect(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(SoundKind::class)],
            'description' => ['required', 'string', 'max:190'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'start_offset_seconds' => ['nullable', 'numeric', 'min:0', 'max:180'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0.5', 'max:30'],
            'volume' => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        return [
            'kind' => $data['kind'],
            'description' => $data['description'],
            'prompt' => $data['prompt'] ?: $data['description'],
            'start_offset_ms' => (int) round(($data['start_offset_seconds'] ?? 0) * 1000),
            'duration_ms' => isset($data['duration_seconds']) ? (int) round($data['duration_seconds'] * 1000) : null,
            'volume' => (float) $data['volume'],
            'enabled' => $request->boolean('enabled', true),
            'loop' => $data['kind'] === SoundKind::Ambience->value,
        ];
    }

    public static function musicLabel(?StoryAudioAsset $asset): string
    {
        return $asset ? ($asset->text ?: 'موسیقی') : 'بدون موسیقی';
    }
}
