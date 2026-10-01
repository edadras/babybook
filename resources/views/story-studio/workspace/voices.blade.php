@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'voices')

@section('canvas')
    <div class="canvas-head">
        <h1>صداگذاری شخصیت‌ها</h1>
        <span class="muted">هر شخصیت یک صدای ثابت دارد که در کل پروژه دوباره استفاده می‌شود.</span>
        <span class="grow"></span>
        @if ($project->mode->value === 'manual' && ! $project->voices_approved_at)
            <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, 'voices']) }}">@csrf<button class="btn btn-primary btn-sm">✓ تأیید و قفل صداها</button></form>
        @endif
    </div>

    @if ($libraryError)
        <div class="alert warn">کتابخانه صداها در دسترس نیست: {{ \Illuminate\Support\Str::limit($libraryError, 160) }}</div>
    @endif

    <div class="voice-grid">
        @foreach ($characters as $character)
            @php($front = $character->referenceAssets->where('type', \App\Enums\StoryStudio\AssetType::CharacterFront)->firstWhere('selected', true))
            @php($speaks = $character->is_narrator || $speakerIds->contains($character->id))
            <article @class(['panel', 'voice-card', 'muted-card' => ! $speaks]) id="c-{{ $character->id }}">
                <header class="voice-head">
                    <div class="avatar">
                        @if ($front)
                            <img src="{{ route('story-studio.files.show', ['asset', $front->id]) }}" alt="" loading="lazy">
                        @else
                            <span>{{ $character->is_narrator ? '🎙' : mb_substr($character->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div>
                        <h2>{{ $character->name }} @if ($character->is_narrator)<span class="tag">راوی</span>@endif</h2>
                        <p class="subtle">{{ $speaks ? ($character->is_narrator ? 'روایت همه صحنه‌ها' : Fa::digits($character->dialogues_count).' دیالوگ') : 'دیالوگ ندارد' }}</p>
                    </div>
                    <span class="grow"></span>
                    @if ($character->voiceProfile?->locked)
                        <span class="pill tone-success">🔒 قفل</span>
                    @else
                        @include('story-studio.partials.state', ['state' => $character->voice_status])
                    @endif
                </header>

                <form method="post" action="{{ route('story-studio.projects.voices.design', [$project, $character]) }}" class="voice-design">
                    @csrf
                    <label class="label">کارگردانی صدا</label>
                    <textarea class="textarea ltr-input" rows="2" name="voice_description" @disabled($character->voiceProfile?->locked)>{{ $character->voice_description }}</textarea>
                    @unless ($character->voiceProfile?->locked)
                        <button class="btn btn-sm">✦ ساخت صداهای پیشنهادی</button>
                    @endunless
                </form>

                @if ($character->voiceProfile)
                    <div class="current-voice">
                        <span class="label">صدای انتخاب‌شده</span>
                        <strong>{{ $character->voiceProfile->name }}</strong>
                        <code class="ltr">{{ $character->voiceProfile->provider_voice_id }}</code>
                        @include('story-studio.partials.audio', ['asset' => $character->voiceProfile->preview, 'empty' => 'بدون نمونه'])
                        <div class="row-actions">
                            <form method="post" action="{{ route('story-studio.projects.voices.preview', [$project, $character->voiceProfile]) }}">@csrf<button class="btn btn-sm btn-ghost">▶ ساخت نمونه</button></form>
                            <form method="post" action="{{ route('story-studio.projects.voices.lock', [$project, $character]) }}">@csrf
                                @if ($character->voiceProfile->locked)
                                    <input type="hidden" name="unlock" value="1"><button class="btn btn-sm">🔓 باز کردن قفل</button>
                                @else
                                    <button class="btn btn-sm btn-dark">🔒 قفل صدا</button>
                                @endif
                            </form>
                        </div>
                    </div>
                @endif

                @php($candidates = $character->voiceCandidates->where('id', '!=', $character->voice_profile_id)->sortByDesc('created_at')->take(6))
                @if ($candidates->isNotEmpty())
                    <div class="candidates">
                        <span class="label">صداهای پیشنهادی — گوش دهید و یکی را انتخاب کنید</span>
                        @foreach ($candidates as $candidate)
                            <div class="candidate">
                                <span>{{ $candidate->name }}</span>
                                @include('story-studio.partials.audio', ['asset' => $candidate->preview])
                                @unless ($character->voiceProfile?->locked)
                                    <form method="post" action="{{ route('story-studio.projects.voices.select', [$project, $candidate]) }}">@csrf<button class="btn btn-sm btn-primary">انتخاب</button></form>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif

                @unless ($character->voiceProfile?->locked)
                    <details class="library">
                        <summary>انتخاب از کتابخانه صداها / شناسه صدا</summary>
                        <form method="post" action="{{ route('story-studio.projects.voices.library', [$project, $character]) }}" class="inline-form">
                            @csrf
                            @if ($library)
                                <select class="select" name="voice_id" onchange="this.form.name.value=this.options[this.selectedIndex].dataset.name||''">
                                    @foreach ($library as $voice)
                                        <option value="{{ $voice['voice_id'] }}" data-name="{{ $voice['name'] }}">{{ $voice['name'] }}{{ $voice['description'] ? ' — '.\Illuminate\Support\Str::limit($voice['description'], 40) : '' }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="name" value="{{ $library[0]['name'] ?? '' }}">
                            @else
                                <input class="input ltr-input" name="voice_id" placeholder="ElevenLabs voice_id" required>
                                <input class="input" name="name" placeholder="نام صدا">
                            @endif
                            <button class="btn btn-sm">اختصاص</button>
                        </form>
                    </details>
                @endunless
            </article>
        @endforeach
    </div>
@endsection
