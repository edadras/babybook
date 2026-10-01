@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@use('App\Enums\StoryStudio\AssetType')
@use('App\Enums\StoryStudio\AudioAssetType')
@section('tab', 'storyboard')

@section('canvas')
    <div class="canvas-head">
        <h1>استوری‌بورد</h1>
        <span class="muted">{{ Fa::digits($scenes->count()) }} صحنه · برای تغییر ترتیب، کارت‌ها را بکشید.</span>
        <span class="grow"></span>
        @if ($project->mode->value === 'manual' && $scenes->isNotEmpty() && ! $project->storyboard_approved_at)
            <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, $project->script_approved_at ? 'storyboard' : 'script']) }}">@csrf
                <button class="btn btn-primary btn-sm">✓ {{ $project->script_approved_at ? 'تأیید استوری‌بورد' : 'تأیید فیلمنامه' }}</button>
            </form>
        @endif
    </div>

    @if ($scenes->isEmpty())
        <div class="empty small"><p>صحنه‌ها پس از تحلیل داستان ساخته می‌شوند.</p></div>
    @endif

    <div class="storyboard" data-reorder-url="{{ route('story-studio.projects.scenes.reorder', $project) }}">
        @foreach ($scenes as $scene)
            @php
                $images = $scene->assets->where('type', AssetType::SceneImage)->sortByDesc('version');
                $image = $images->firstWhere('selected', true);
                $video = $scene->assets->where('type', AssetType::SceneVideo)->firstWhere('selected', true);
                $narration = $scene->audioAssets->where('type', AudioAssetType::Narration)->first();
                $open = $focus === $scene->id;
            @endphp
            <article class="scene-card" draggable="true" data-scene-id="{{ $scene->id }}" id="s-{{ $scene->id }}">
                <div class="scene-media">
                    @if ($video && $scene->workState('video')->value === 'ready')
                        <video controls preload="none" poster="{{ $image ? route('story-studio.files.show', ['asset', $image->id]) : '' }}" src="{{ route('story-studio.files.show', ['asset', $video->id]) }}"></video>
                    @elseif ($image)
                        <img src="{{ route('story-studio.files.show', ['asset', $image->id]) }}" alt="صحنه {{ $scene->scene_number }}" loading="lazy">
                    @else
                        <div class="placeholder">{{ in_array($scene->workState('image')->value, ['queued', 'generating']) ? 'در حال ساخت تصویر…' : 'بدون تصویر' }}</div>
                    @endif
                    <span class="scene-num">{{ Fa::digits($scene->scene_number) }}</span>
                    <span class="drag-handle" title="کشیدن برای جابه‌جایی">⋮⋮</span>
                </div>

                <div class="scene-body">
                    <header>
                        <h2>{{ $scene->title }}</h2>
                        <span class="subtle">{{ $scene->location?->name }} · {{ $scene->time_of_day }} · {{ Fa::duration($scene->duration_ms ?? $scene->duration_estimate_ms) }}</span>
                    </header>
                    <div class="state-row">
                        @include('story-studio.partials.state', ['state' => $scene->workState('image'), 'label' => 'تصویر'])
                        @include('story-studio.partials.state', ['state' => $scene->workState('narration'), 'label' => 'روایت'])
                        @include('story-studio.partials.state', ['state' => $scene->workState('mix'), 'label' => 'میکس'])
                        @include('story-studio.partials.state', ['state' => $scene->workState('video'), 'label' => 'ویدیو'])
                    </div>

                    <p class="narration">{{ $scene->narration_original ?: '—' }}</p>
                    @include('story-studio.partials.audio', ['asset' => $narration, 'empty' => 'روایت هنوز ضبط نشده'])

                    @if ($scene->dialogues->isNotEmpty())
                        <ul class="dialogues">
                            @foreach ($scene->dialogues as $dialogue)
                                <li>
                                    <strong>{{ $dialogue->character?->name ?? 'بی‌نام' }}:</strong> «{{ $dialogue->text_original }}»
                                    <span class="tag ltr">{{ $dialogue->emotion }}{{ $dialogue->delivery ? ' · '.$dialogue->delivery : '' }}</span>
                                    @include('story-studio.partials.state', ['state' => $dialogue->status])
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="chips-row">
                        @foreach ($scene->characters as $character)<span class="tag">🧸 {{ $character->name }}</span>@endforeach
                        @foreach ($scene->soundEffects as $effect)<span @class(['tag', 'off' => ! $effect->enabled])>🔊 {{ \Illuminate\Support\Str::limit($effect->description, 26) }}</span>@endforeach
                        @if ($scene->music_mood)<span class="tag">🎵 {{ $scene->music_mood }}</span>@endif
                    </div>

                    @if ($images->count() > 1)
                        <div class="versions">
                            <span class="subtle">نسخه‌ها:</span>
                            @foreach ($images as $version)
                                <form method="post" action="{{ route('story-studio.projects.assets.select', [$project, $version]) }}">@csrf
                                    <button @class(['vthumb', 'on' => $version->selected]) title="انتخاب نسخه {{ $version->version }}">
                                        <img src="{{ route('story-studio.files.show', ['asset', $version->id]) }}" alt="v{{ $version->version }}" loading="lazy"><span>v{{ $version->version }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    <div class="scene-actions">
                        <form method="post" action="{{ route('story-studio.projects.scenes.image', [$project, $scene]) }}">@csrf<button class="btn btn-sm">↻ تولید مجدد تصویر</button></form>
                        <form method="post" action="{{ route('story-studio.projects.scenes.image', [$project, $scene]) }}">@csrf<input type="hidden" name="variations" value="3"><input type="hidden" name="keep_selection" value="1"><button class="btn btn-sm btn-ghost">✦ ۳ واریاسیون</button></form>
                        <form method="post" action="{{ route('story-studio.projects.scenes.narration', [$project, $scene]) }}">@csrf<button class="btn btn-sm btn-ghost">🎙 تولید مجدد صدا</button></form>
                        <form method="post" action="{{ route('story-studio.projects.scenes.preview', [$project, $scene]) }}">@csrf<button class="btn btn-sm btn-ghost" @disabled(! $image)>▶ پیش‌نمایش صحنه</button></form>
                        <a class="btn btn-sm btn-ghost" href="{{ route('story-studio.projects.sound', $project) }}#s-{{ $scene->id }}">🔊 افکت صوتی</a>
                        <a class="btn btn-sm btn-ghost" href="{{ route('story-studio.projects.voices', $project) }}">🗣 تغییر صدا</a>
                        <form method="post" action="{{ route('story-studio.projects.scenes.split', [$project, $scene]) }}">@csrf<button class="btn btn-sm btn-ghost">✂ تقسیم صحنه</button></form>
                        <form method="post" action="{{ route('story-studio.projects.scenes.destroy', [$project, $scene]) }}" data-confirm="صحنه {{ $scene->scene_number }} حذف شود؟">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger">حذف</button></form>
                    </div>

                    <details class="scene-edit" @if ($open) open @endif>
                        <summary>✎ ویرایش متن، تصویر و احساس</summary>

                        <form method="post" action="{{ route('story-studio.projects.scenes.update', [$project, $scene]) }}" class="form-grid">
                            @csrf @method('PUT')
                            <div class="field"><label>عنوان</label><input class="input" name="title" value="{{ $scene->title }}"></div>
                            <div class="field"><label>مکان</label>
                                <select class="select" name="location_id"><option value="">—</option>
                                    @foreach ($locations as $location)<option value="{{ $location->id }}" @selected($scene->location_id === $location->id)>{{ $location->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field span-2"><label>روایت (متن اصلی)</label><textarea class="textarea" rows="3" name="narration_original">{{ $scene->narration_original }}</textarea>
                                @if ($scene->narration_tts && $scene->narration_tts !== $scene->narration_original)<p class="hint">متن ارسالی به TTS: {{ $scene->narration_tts }}</p>@endif
                            </div>
                            <div class="field span-2"><label>ترجمه انگلیسی (زیرنویس)</label><textarea class="textarea ltr-input" rows="2" name="narration_en">{{ $scene->narration_en }}</textarea></div>
                            <div class="field span-2"><label>شخصیت‌های حاضر</label>
                                <div class="check-list">
                                    @foreach ($characters as $character)
                                        <label class="check"><input type="checkbox" name="character_ids[]" value="{{ $character->id }}" @checked($scene->characters->contains($character))> {{ $character->name }}</label>
                                    @endforeach
                                </div>
                                <input type="hidden" name="character_ids[]" value="">
                            </div>
                            <div class="field span-2"><label>توصیف تصویر (کارگردان)</label><textarea class="textarea ltr-input" rows="2" name="director_prompt">{{ $scene->director_prompt }}</textarea></div>
                            <div class="field"><label>نوع نما</label><input class="input ltr-input" name="visual[shot_type]" value="{{ data_get($scene->visual, 'shot_type') }}"></div>
                            <div class="field"><label>زاویه دوربین</label><input class="input ltr-input" name="visual[camera_angle]" value="{{ data_get($scene->visual, 'camera_angle') }}"></div>
                            <div class="field"><label>نور</label><input class="input ltr-input" name="visual[lighting]" value="{{ data_get($scene->visual, 'lighting') }}"></div>
                            <div class="field"><label>حس و حال</label><input class="input ltr-input" name="visual[mood]" value="{{ data_get($scene->visual, 'mood') }}"></div>
                            <div class="field span-2"><label>قاب‌بندی</label><input class="input ltr-input" name="visual[composition]" value="{{ data_get($scene->visual, 'composition') }}"></div>
                            <div class="field span-2"><label>اشیای مهم برای پیوستگی <small class="subtle">(با کاما)</small></label><input class="input ltr-input" name="continuity_props" value="{{ implode(', ', data_get($scene->continuityState?->state, 'props', [])) }}"></div>
                            <div class="field span-2">
                                <label>پرامپت نهایی تصویر {{ $scene->prompt_edited ? '(ویرایش دستی)' : '(ترکیب خودکار)' }}</label>
                                <textarea class="textarea ltr-input mono" rows="8" name="final_image_prompt">{{ $scene->final_image_prompt }}</textarea>
                                @if ($scene->prompt_edited)<label class="check"><input type="checkbox" name="reset_prompt" value="1"> بازگشت به ترکیب خودکار</label>@endif
                            </div>
                            <div class="field"><label>حرکت تصویر</label>
                                <select class="select" name="motion">@foreach (\App\Enums\StoryStudio\ImageMotion::cases() as $m)<option value="{{ $m->value }}" @selected($scene->motion === $m)>{{ $m->label() }}</option>@endforeach</select>
                            </div>
                            <div class="field"><label>ترنزیشن به صحنه بعد</label>
                                <select class="select" name="transition">@foreach (\App\Enums\StoryStudio\Transition::cases() as $t)<option value="{{ $t->value }}" @selected($scene->transition === $t)>{{ $t->label() }}</option>@endforeach</select>
                            </div>
                            <div class="field"><label>مدت صحنه (ثانیه، خالی = خودکار)</label><input class="input ltr-input" type="number" step="0.5" min="2" name="duration_seconds" value="{{ $scene->duration_ms ? $scene->duration_ms / 1000 : '' }}"></div>
                            <div class="field"><label>حال و هوای موسیقی</label>
                                <select class="select" name="music_mood"><option value="">—</option>@foreach (\App\Services\StoryStudio\Schema\StorySchemas::MUSIC_MOODS as $mood)<option @selected($scene->music_mood === $mood)>{{ $mood }}</option>@endforeach</select>
                            </div>
                            <div class="span-2"><button class="btn btn-primary btn-sm">ذخیره صحنه</button></div>
                        </form>

                        <h4>دیالوگ‌ها و احساس</h4>
                        @foreach ($scene->dialogues as $dialogue)
                            <form method="post" action="{{ route('story-studio.projects.dialogues.update', [$project, $dialogue]) }}" class="dialogue-form">
                                @csrf @method('PUT')
                                <select class="select" name="character_id">@foreach ($characters as $character)<option value="{{ $character->id }}" @selected($dialogue->character_id === $character->id)>{{ $character->name }}</option>@endforeach</select>
                                <input class="input grow-2" name="text_original" value="{{ $dialogue->text_original }}" required>
                                <input class="input ltr-input" name="emotion" value="{{ $dialogue->emotion }}" placeholder="emotion" title="احساس">
                                <select class="select" name="energy" title="انرژی">@foreach (['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'] as $v => $l)<option value="{{ $v }}" @selected($dialogue->energy === $v)>{{ $l }}</option>@endforeach</select>
                                <select class="select" name="pace" title="سرعت">@foreach (['slow' => 'آهسته', 'normal' => 'عادی', 'slightly_fast' => 'کمی تند', 'fast' => 'تند'] as $v => $l)<option value="{{ $v }}" @selected($dialogue->pace === $v)>{{ $l }}</option>@endforeach</select>
                                <input class="input ltr-input" name="delivery" value="{{ $dialogue->delivery }}" placeholder="delivery" title="نحوه بیان">
                                <input type="hidden" name="text_en" value="{{ $dialogue->text_en }}">
                                <input type="hidden" name="volume" value="{{ $dialogue->volume }}">
                                <input type="hidden" name="start_offset_seconds" value="{{ $dialogue->start_offset_ms !== null ? $dialogue->start_offset_ms / 1000 : '' }}">
                                <button class="btn btn-sm">ذخیره</button>
                                <button class="btn btn-sm btn-ghost" formaction="{{ route('story-studio.projects.dialogues.regenerate', [$project, $dialogue]) }}" formmethod="post" name="_method" value="POST">🎙</button>
                                @include('story-studio.partials.audio', ['asset' => $dialogue->audioAssets()->selected()->first(), 'empty' => ''])
                            </form>
                            <form method="post" action="{{ route('story-studio.projects.dialogues.destroy', [$project, $dialogue]) }}" class="inline-delete" data-confirm="این دیالوگ حذف شود؟">@csrf @method('DELETE')<button class="link-btn danger">حذف دیالوگ</button></form>
                        @endforeach
                        <form method="post" action="{{ route('story-studio.projects.dialogues.store', [$project, $scene]) }}" class="dialogue-form add">
                            @csrf
                            <select class="select" name="character_id">@foreach ($characters as $character)<option value="{{ $character->id }}">{{ $character->name }}</option>@endforeach</select>
                            <input class="input grow-2" name="text_original" placeholder="دیالوگ تازه…" required>
                            <input class="input ltr-input" name="emotion" placeholder="emotion">
                            <input class="input ltr-input" name="delivery" placeholder="delivery">
                            <button class="btn btn-sm btn-dark">＋ افزودن</button>
                        </form>
                    </details>
                </div>
            </article>
        @endforeach
    </div>
@endsection
