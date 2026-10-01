<?php

namespace App\Http\Controllers\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Jobs\StoryStudio\GenerateCharacterBibleJob;
use App\Jobs\StoryStudio\GenerateCharacterReferenceJob;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\CharacterReferenceService;
use App\Services\StoryStudio\DependencyInvalidator;
use App\Services\StoryStudio\ImageDirectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharacterController extends StudioController
{
    public function index(Request $request, StoryProject $project, ImageDirectorService $director): View
    {
        $this->authorizeProject($request, $project, 'view');
        $characters = $project->characters()->with(['referenceAssets', 'voiceProfile'])->withCount(['dialogues', 'scenes'])->get();

        return view('story-studio.workspace.characters', [
            'project' => $project,
            'characters' => $characters,
            'views' => CharacterReferenceService::REFERENCE_VIEWS,
            'promptFor' => fn (StoryCharacter $c) => $director->characterReferencePrompt($c, AssetType::CharacterFront),
            'locations' => $project->locations,
        ]);
    }

    public function update(Request $request, StoryProject $project, StoryCharacter $character, DependencyInvalidator $invalidator): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_if($character->character_locked, 409, 'این شخصیت قفل است؛ ابتدا قفل را باز کنید.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'personality' => ['nullable', 'string', 'max:2000'],
            'visual_description' => ['nullable', 'string', 'max:3000'],
            'clothing' => ['nullable', 'string', 'max:2000'],
            'hair' => ['nullable', 'string', 'max:1000'],
            'face' => ['nullable', 'string', 'max:1000'],
            'colors' => ['nullable', 'string', 'max:500'],
            'height_relation' => ['nullable', 'string', 'max:255'],
            'special_features' => ['nullable', 'string', 'max:2000'],
            'voice_description' => ['nullable', 'string', 'max:2000'],
            'reference_prompt' => ['nullable', 'string', 'max:6000'],
            'is_major' => ['sometimes', 'boolean'],
        ]);
        $data['colors'] = array_values(array_filter(array_map('trim', explode(',', str_replace('،', ',', (string) ($data['colors'] ?? ''))))));
        $data['is_major'] = $request->boolean('is_major');

        $character->fill($data);
        $designChanged = $character->isDirty([...StoryCharacter::VISUAL_IDENTITY_FIELDS, 'reference_prompt']);
        $character->save();

        if ($designChanged) {
            $invalidator->characterDesignChanged($character);
        }

        return $this->done($designChanged
            ? 'کتاب شخصیت به‌روز شد؛ تصاویر مرجع و صحنه‌های این شخصیت نیاز به بازسازی دارند.'
            : 'تغییرات ذخیره شد.');
    }

    public function references(Request $request, StoryProject $project, StoryCharacter $character): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_if($character->character_locked, 409, 'شخصیت قفل است؛ برای تولید مجدد قفل را باز کنید.');
        $data = $request->validate(['view' => ['nullable', Rule::in(array_map(fn ($v) => $v->value, CharacterReferenceService::REFERENCE_VIEWS))]]);

        $character->forceFill(['status' => AssetStatus::Queued])->save();
        GenerateCharacterReferenceJob::dispatch($project->getKey(), $character->getKey(), $this->nonce(), $data['view'] ?? null);

        return $this->done('ساخت تصاویر مرجع «'.$character->name.'» در صف قرار گرفت.');
    }

    public function refine(Request $request, StoryProject $project, StoryCharacter $character): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_if($character->character_locked, 409, 'شخصیت قفل است.');
        GenerateCharacterBibleJob::dispatch($project->getKey(), $character->getKey(), $this->nonce());

        return $this->done('کارگردان در حال دقیق‌تر کردن کتاب شخصیت است.');
    }

    public function lock(Request $request, StoryProject $project, StoryCharacter $character): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $request->boolean('unlock') ? $character->unlock() : $character->lock();

        return $this->done($request->boolean('unlock') ? 'قفل شخصیت باز شد.' : 'شخصیت قفل شد و ظاهرش در همه صحنه‌ها ثابت می‌ماند.');
    }

    /**
     * Select any visual asset version (character reference or scene image).
     */
    public function selectAsset(Request $request, StoryProject $project, StoryAsset $asset, DependencyInvalidator $invalidator): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->ensureBelongs($project, $asset);
        $asset->select();

        if ($asset->type === AssetType::SceneImage && $asset->scene) {
            $invalidator->imageSelected($asset->scene);
        }
        if ($asset->type === AssetType::CharacterFront && $asset->character) {
            $asset->character->forceFill(['reference_asset_id' => $asset->getKey()])->save();
        }

        return $this->done('نسخه '.$asset->version.' انتخاب شد.');
    }
}
