@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'timeline')

@php
    $total = max(1, $timeline['total_ms']);
    $pct = fn (int $ms) => round($ms / $total * 100, 3);
    $tracks = ['IMAGE / VISUAL' => 'image', 'NARRATOR' => 'narrator', 'CHARACTERS' => 'characters', 'SFX' => 'sfx', 'AMBIENCE' => 'ambience', 'MUSIC' => 'music', 'SUBTITLES' => 'subtitles'];
@endphp

@section('canvas')
    <div class="canvas-head">
        <h1>تایم‌لاین</h1>
        <span class="muted">{{ Fa::digits(count($timeline['scenes'])) }} صحنه · {{ Fa::duration($timeline['total_ms']) }} · برای ویرایش روی یک صحنه کلیک کنید.</span>
    </div>

    @if (! $timeline['scenes'])
        <div class="empty small"><p>پس از ساخت صحنه‌ها، تایم‌لاین اینجا نمایش داده می‌شود.</p></div>
    @else
        <div class="timeline-editor">
            <div class="ruler ltr-track">
                @for ($t = 0; $t <= $timeline['total_ms']; $t += max(5000, (int) (ceil($timeline['total_ms'] / 12 / 5000) * 5000)))
                    <span style="left: {{ $pct($t) }}%">{{ $t === 0 ? '۰:۰۰' : Fa::duration($t) }}</span>
                @endfor
            </div>
            @foreach ($tracks as $label => $track)
                <div class="tl-track">
                    <span class="tl-name">{{ $label }}</span>
                    <div class="tl-lane ltr-track">
                        @foreach ($timeline['scenes'] as $row)
                            @php($start = $row['start_ms'])
                            @php($isSel = $selected && $selected['id'] === $row['id'])
                            @if ($track === 'image')
                                <a href="{{ route('story-studio.projects.timeline', [$project, 'scene' => $row['id']]) }}" @class(['blk', 'blk-image', 'sel' => $isSel]) style="left: {{ $pct($start) }}%; width: {{ $pct($row['duration_ms']) }}%">
                                    @if ($row['image_id'])<img src="{{ route('story-studio.files.show', ['asset', $row['image_id']]) }}" alt="" loading="lazy">@endif
                                    <span>{{ Fa::digits($row['number']) }}</span>
                                </a>
                            @elseif ($track === 'narrator' || $track === 'characters' || $track === 'subtitles')
                                @foreach ($row['plan']['voice'] as $item)
                                    @if (($track === 'narrator' && $item['kind'] === 'narration') || ($track === 'characters' && $item['kind'] === 'dialogue') || $track === 'subtitles')
                                        <span @class(['blk', 'blk-'.$track, 'est' => $item['estimated']]) style="left: {{ $pct($start + $item['start_ms']) }}%; width: {{ $pct($item['duration_ms']) }}%" title="{{ $item['label'] }}: {{ \Illuminate\Support\Str::limit($item['text'], 80) }}"></span>
                                    @endif
                                @endforeach
                            @elseif ($track === 'sfx' || $track === 'ambience')
                                @foreach ($row['plan']['effects'] as $item)
                                    @if (($track === 'ambience') === ($item['kind'] === 'ambience'))
                                        <span @class(['blk', 'blk-'.$track, 'off' => ! $item['enabled']]) style="left: {{ $pct($start + $item['start_ms']) }}%; width: {{ $pct(max(300, $item['duration_ms'])) }}%" title="{{ $item['label'] }}"></span>
                                    @endif
                                @endforeach
                            @elseif ($track === 'music' && $row['plan']['music'])
                                <span class="blk blk-music" style="left: {{ $pct($start) }}%; width: {{ $pct($row['duration_ms']) }}%" title="{{ $row['plan']['music']['mood'] }}"></span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <p class="subtle">بلوک‌های کم‌رنگ هنوز صدا ندارند و مدتشان تخمینی است.</p>
    @endif
@endsection

@section('inspector')
    @if ($selected && $scene)
        <section class="inspector-section">
            <h3>صحنه {{ Fa::digits($scene->scene_number) }} — {{ Fa::duration($selected['duration_ms']) }}</h3>
            <form method="post" action="{{ route('story-studio.projects.timeline.update', [$project, $scene]) }}" class="stack-form">
                @csrf @method('PUT')
                <label>مدت صحنه (ثانیه، خالی = خودکار) <input class="input ltr-input" type="number" step="0.1" min="2" name="duration_seconds" value="{{ $scene->duration_ms ? $scene->duration_ms / 1000 : '' }}" placeholder="{{ round($selected['duration_ms'] / 1000, 1) }}"></label>
                <label>حرکت تصویر
                    <select class="select" name="motion">@foreach (\App\Enums\StoryStudio\ImageMotion::cases() as $m)<option value="{{ $m->value }}" @selected($scene->motion === $m)>{{ $m->label() }}</option>@endforeach</select>
                </label>
                <label>ترنزیشن به بعدی
                    <select class="select" name="transition">@foreach (\App\Enums\StoryStudio\Transition::cases() as $t)<option value="{{ $t->value }}" @selected($scene->transition === $t)>{{ $t->label() }}</option>@endforeach</select>
                </label>
                <label>مدت ترنزیشن (ms) <input class="input ltr-input" type="number" min="0" max="3000" step="50" name="transition_duration_ms" value="{{ $scene->transition_duration_ms }}" placeholder="{{ $project->settings?->transition_duration_ms }}"></label>
                <fieldset>
                    <legend>موسیقی</legend>
                    <label>بلندی <input class="input ltr-input" type="number" step="0.05" min="0" max="1.5" name="music_volume" value="{{ $scene->music_volume }}" placeholder="{{ $project->settings?->music_volume }}"></label>
                    <label>فید ورود (s) <input class="input ltr-input" type="number" step="0.5" min="0" name="music_fade_in_seconds" value="{{ $scene->music_fade_in_ms / 1000 }}"></label>
                    <label>فید خروج (s) <input class="input ltr-input" type="number" step="0.5" min="0" name="music_fade_out_seconds" value="{{ $scene->music_fade_out_ms / 1000 }}"></label>
                </fieldset>
                @if ($scene->dialogues->isNotEmpty())
                    <fieldset>
                        <legend>دیالوگ‌ها — شروع (s) / بلندی</legend>
                        @foreach ($scene->dialogues as $dialogue)
                            <div class="pair">
                                <span title="{{ $dialogue->text_original }}">{{ $dialogue->character?->name }}</span>
                                <input class="input tiny ltr-input" type="number" step="0.1" min="0" name="dialogues[{{ $dialogue->id }}][start_offset_seconds]" value="{{ $dialogue->start_offset_ms !== null ? $dialogue->start_offset_ms / 1000 : '' }}" placeholder="خودکار">
                                <input class="input tiny ltr-input" type="number" step="0.05" min="0" max="2" name="dialogues[{{ $dialogue->id }}][volume]" value="{{ $dialogue->volume }}">
                            </div>
                        @endforeach
                    </fieldset>
                @endif
                @if ($scene->soundEffects->isNotEmpty())
                    <fieldset>
                        <legend>افکت‌ها — فعال / شروع (s) / بلندی</legend>
                        @foreach ($scene->soundEffects as $effect)
                            <div class="pair">
                                <input type="hidden" name="effects[{{ $effect->id }}][enabled]" value="0">
                                <label class="check"><input type="checkbox" name="effects[{{ $effect->id }}][enabled]" value="1" @checked($effect->enabled)> {{ \Illuminate\Support\Str::limit($effect->description, 18) }}</label>
                                <input class="input tiny ltr-input" type="number" step="0.1" min="0" name="effects[{{ $effect->id }}][start_offset_seconds]" value="{{ $effect->start_offset_ms / 1000 }}">
                                <input class="input tiny ltr-input" type="number" step="0.05" min="0" max="2" name="effects[{{ $effect->id }}][volume]" value="{{ $effect->volume }}">
                            </div>
                        @endforeach
                    </fieldset>
                @endif
                <button class="btn btn-primary btn-sm">ذخیره زمان‌بندی</button>
            </form>
            <form method="post" action="{{ route('story-studio.projects.scenes.preview', [$project, $scene]) }}" style="margin-top:8px">@csrf<button class="btn btn-sm btn-block">▶ رندر پیش‌نمایش صحنه</button></form>
        </section>
    @endif
@endsection
