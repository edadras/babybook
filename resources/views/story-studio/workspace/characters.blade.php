@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'characters')

@section('canvas')
    <div class="canvas-head">
        <h1>شخصیت‌ها و کتاب شخصیت</h1>
        <span class="muted">ظاهر هر شخصیت قفل می‌شود و عیناً در همه تصاویر تزریق می‌شود.</span>
        <span class="grow"></span>
        @if ($project->mode->value === 'manual' && ! $project->characters_approved_at && $characters->where('is_narrator', false)->isNotEmpty())
            <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, 'characters']) }}">@csrf<button class="btn btn-primary btn-sm">✓ تأیید و قفل همه شخصیت‌ها</button></form>
        @endif
    </div>

    @if ($characters->isEmpty())
        <div class="empty small"><p>پس از تحلیل داستان، شخصیت‌ها اینجا نمایش داده می‌شوند.</p></div>
    @endif

    @foreach ($characters->where('is_narrator', false) as $character)
        <article class="panel character" id="c-{{ $character->id }}">
            <header class="character-head">
                <div>
                    <h2>{{ $character->name }} <span class="tag ltr">{{ $character->key }}</span></h2>
                    <p class="subtle">{{ $character->role }} · {{ $character->is_major ? 'شخصیت اصلی' : 'فرعی' }} · {{ Fa::digits($character->scenes_count) }} صحنه · {{ Fa::digits($character->dialogues_count) }} دیالوگ</p>
                </div>
                <span class="grow"></span>
                @include('story-studio.partials.state', ['state' => $character->status, 'label' => 'تصاویر مرجع:'])
                @if ($character->character_locked)
                    <span class="pill tone-success">🔒 قفل شده</span>
                @endif
            </header>

            <div class="reference-row">
                @foreach ($views as $view)
                    @php($versions = $character->referenceAssets->where('type', $view)->sortByDesc('version'))
                    @php($selected = $versions->firstWhere('selected', true))
                    <figure class="reference">
                        <div class="ref-frame">
                            @if ($selected)
                                <img src="{{ route('story-studio.files.show', ['asset', $selected->id]) }}" alt="{{ $character->name }} — {{ $view->label() }}" loading="lazy">
                            @else
                                <span class="placeholder">{{ $view->label() }}</span>
                            @endif
                        </div>
                        <figcaption>
                            {{ $view->label() }}
                            @if ($versions->count() > 1)
                                <span class="versions">
                                    @foreach ($versions as $version)
                                        <form method="post" action="{{ route('story-studio.projects.assets.select', [$project, $version]) }}">@csrf
                                            <button @class(['vchip', 'on' => $version->selected]) title="انتخاب نسخه {{ $version->version }}">v{{ $version->version }}</button>
                                        </form>
                                    @endforeach
                                </span>
                            @endif
                            @unless ($character->character_locked)
                                <form method="post" action="{{ route('story-studio.projects.characters.references', [$project, $character]) }}">@csrf
                                    <input type="hidden" name="view" value="{{ $view->value }}">
                                    <button class="link-btn">↻ تولید مجدد</button>
                                </form>
                            @endunless
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="row-actions">
                @if ($character->character_locked)
                    <form method="post" action="{{ route('story-studio.projects.characters.lock', [$project, $character]) }}">@csrf<input type="hidden" name="unlock" value="1"><button class="btn btn-sm">🔓 باز کردن قفل</button></form>
                @else
                    <form method="post" action="{{ route('story-studio.projects.characters.lock', [$project, $character]) }}">@csrf<button class="btn btn-sm btn-dark">🔒 تأیید و قفل</button></form>
                    <form method="post" action="{{ route('story-studio.projects.characters.references', [$project, $character]) }}">@csrf<button class="btn btn-sm">↻ تولید هر سه نما</button></form>
                    <form method="post" action="{{ route('story-studio.projects.characters.refine', [$project, $character]) }}">@csrf<button class="btn btn-sm btn-ghost">✦ دقیق‌تر کردن توصیف با هوش مصنوعی</button></form>
                @endif
                <a class="btn btn-sm btn-ghost" href="{{ route('story-studio.projects.voices', $project) }}#c-{{ $character->id }}">🎙 صدای شخصیت</a>
            </div>

            <details class="bible" @if ($errors->any() && old('_character') === $character->id) open @endif>
                <summary>کتاب شخصیت (Character Bible) {{ $character->character_locked ? '— فقط خواندنی' : '— ویرایش' }}</summary>
                <form method="post" action="{{ route('story-studio.projects.characters.update', [$project, $character]) }}" class="form-grid">
                    @csrf @method('PUT')
                    <input type="hidden" name="_character" value="{{ $character->id }}">
                    @php($ro = $character->character_locked)
                    <div class="field"><label>نام</label><input class="input" name="name" value="{{ old('name', $character->name) }}" @readonly($ro)></div>
                    <div class="field"><label>رنگ‌های شاخص <small class="subtle">(با کاما)</small></label><input class="input ltr-input" name="colors" value="{{ implode(', ', $character->colors ?? []) }}" @readonly($ro)></div>
                    <div class="field span-2"><label>شخصیت و رفتار</label><textarea class="textarea ltr-input" rows="2" name="personality" @readonly($ro)>{{ $character->personality }}</textarea></div>
                    <div class="field span-2"><label>هویت بصری</label><textarea class="textarea ltr-input" rows="3" name="visual_description" @readonly($ro)>{{ $character->visual_description }}</textarea></div>
                    <div class="field"><label>لباس</label><textarea class="textarea ltr-input" rows="2" name="clothing" @readonly($ro)>{{ $character->clothing }}</textarea></div>
                    <div class="field"><label>مو / پوشش</label><textarea class="textarea ltr-input" rows="2" name="hair" @readonly($ro)>{{ $character->hair }}</textarea></div>
                    <div class="field"><label>صورت</label><textarea class="textarea ltr-input" rows="2" name="face" @readonly($ro)>{{ $character->face }}</textarea></div>
                    <div class="field"><label>نسبت قد</label><input class="input ltr-input" name="height_relation" value="{{ $character->height_relation }}" @readonly($ro)></div>
                    <div class="field span-2"><label>ویژگی‌های خاص</label><textarea class="textarea ltr-input" rows="2" name="special_features" @readonly($ro)>{{ $character->special_features }}</textarea></div>
                    <div class="field span-2"><label>توصیف صدا</label><textarea class="textarea ltr-input" rows="2" name="voice_description" @readonly($ro)>{{ $character->voice_description }}</textarea></div>
                    <div class="field span-2">
                        <label>پرامپت سفارشی تصویر مرجع <small class="subtle">(خالی = ترکیب خودکار)</small></label>
                        <textarea class="textarea ltr-input mono" rows="4" name="reference_prompt" placeholder="{{ \Illuminate\Support\Str::limit($promptFor($character), 400) }}" @readonly($ro)>{{ $character->reference_prompt }}</textarea>
                    </div>
                    <label class="check span-2"><input type="checkbox" name="is_major" value="1" @checked($character->is_major) @disabled($ro)> شخصیت اصلی (تصویر مرجع می‌گیرد)</label>
                    @unless ($ro)
                        <div class="span-2"><button class="btn btn-primary btn-sm">ذخیره کتاب شخصیت</button></div>
                    @endunless
                </form>
            </details>
        </article>
    @endforeach
@endsection

@section('inspector')
    <section class="inspector-section">
        <h3>مکان‌ها</h3>
        @forelse ($locations as $location)
            <div class="kv-block"><strong>{{ $location->name }}</strong><p class="subtle ltr-text">{{ \Illuminate\Support\Str::limit($location->visual_description, 140) }}</p></div>
        @empty
            <p class="subtle">—</p>
        @endforelse
    </section>
@endsection
