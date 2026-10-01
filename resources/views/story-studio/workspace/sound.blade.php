@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'sound')

@php($settings = $project->settings)

@section('canvas')
    <div class="canvas-head">
        <h1>صداگذاری</h1>
        <span class="muted">افکت‌ها باید به داستان کمک کنند، نه اینکه حواس را از روایت پرت کنند.</span>
    </div>

    <section class="panel">
        <h2 class="panel-title">کتابخانه موسیقی</h2>
        <p class="subtle">موسیقی بدون حق نشر را بارگذاری کنید. موسیقی هنگام صحبت راوی و شخصیت‌ها به‌طور خودکار پایین می‌آید (Ducking).</p>
        <form method="post" action="{{ route('story-studio.projects.music.upload', $project) }}" enctype="multipart/form-data" class="inline-form">
            @csrf
            <input type="file" name="music" accept="audio/*" required>
            <input class="input" name="name" placeholder="نام قطعه">
            <select class="select" name="mood"><option value="">حال و هوا</option>@foreach ($moods as $mood)<option>{{ $mood }}</option>@endforeach</select>
            <input class="input" name="license" placeholder="مجوز / منبع">
            <button class="btn btn-sm btn-dark">⬆ بارگذاری</button>
        </form>
        @if ($music->isNotEmpty())
            <table class="table">
                <thead><tr><th>قطعه</th><th>حال و هوا</th><th>مدت</th><th>پخش</th><th></th></tr></thead>
                @foreach ($music as $track)
                    <tr>
                        <td>{{ $track->text }} @if ($settings->default_music_audio_id === $track->id)<span class="pill tone-success">پیش‌فرض</span>@endif</td>
                        <td class="ltr">{{ data_get($track->metadata, 'mood', '—') }}</td>
                        <td>{{ Fa::duration($track->duration_ms) }}</td>
                        <td>@include('story-studio.partials.audio', ['asset' => $track])</td>
                        <td>
                            <form method="post" action="{{ route('story-studio.projects.music.assign', $project) }}">@csrf
                                <input type="hidden" name="music_audio_id" value="{{ $settings->default_music_audio_id === $track->id ? '' : $track->id }}">
                                <button class="btn btn-sm btn-ghost">{{ $settings->default_music_audio_id === $track->id ? 'حذف پیش‌فرض' : 'پیش‌فرض پروژه' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </section>

    @foreach ($scenes as $scene)
        <section class="panel sound-scene" id="s-{{ $scene->id }}">
            <header class="character-head">
                <h2>صحنه {{ Fa::digits($scene->scene_number) }} — {{ $scene->title }}</h2>
                <span class="grow"></span>
                <span class="tag">🎵 {{ $scene->music_mood ?? '—' }}</span>
            </header>

            <form method="post" action="{{ route('story-studio.projects.music.assign', $project) }}" class="inline-form music-row">
                @csrf <input type="hidden" name="scene_id" value="{{ $scene->id }}">
                <label class="label">موسیقی صحنه</label>
                <select class="select" name="music_audio_id"><option value="">پیش‌فرض پروژه</option>@foreach ($music as $track)<option value="{{ $track->id }}" @selected($scene->music_audio_id === $track->id)>{{ $track->text }}</option>@endforeach</select>
                <label>بلندی <input class="input tiny ltr-input" type="number" step="0.05" min="0" max="1.5" name="music_volume" value="{{ $scene->music_volume }}" placeholder="{{ $settings->music_volume }}"></label>
                <label>فید ورود <input class="input tiny ltr-input" type="number" step="0.5" min="0" name="music_fade_in_seconds" value="{{ $scene->music_fade_in_ms / 1000 }}"></label>
                <label>فید خروج <input class="input tiny ltr-input" type="number" step="0.5" min="0" name="music_fade_out_seconds" value="{{ $scene->music_fade_out_ms / 1000 }}"></label>
                <button class="btn btn-sm">ذخیره</button>
            </form>

            @foreach ($scene->soundEffects as $effect)
                <form method="post" action="{{ route('story-studio.projects.effects.update', [$project, $effect]) }}" @class(['effect-row', 'off' => ! $effect->enabled])>
                    @csrf @method('PUT')
                    <label class="switch" title="فعال/غیرفعال"><input type="checkbox" name="enabled" value="1" @checked($effect->enabled) onchange="this.form.requestSubmit()"><span></span></label>
                    <select class="select" name="kind">@foreach (\App\Enums\StoryStudio\SoundKind::cases() as $kind)<option value="{{ $kind->value }}" @selected($effect->kind === $kind)>{{ $kind->label() }}</option>@endforeach</select>
                    <input class="input" name="description" value="{{ $effect->description }}" required>
                    <input class="input ltr-input grow-2" name="prompt" value="{{ $effect->prompt }}" placeholder="prompt">
                    <label>شروع <input class="input tiny ltr-input" type="number" step="0.1" min="0" name="start_offset_seconds" value="{{ $effect->start_offset_ms / 1000 }}"></label>
                    <label>بلندی <input class="input tiny ltr-input" type="number" step="0.05" min="0" max="2" name="volume" value="{{ $effect->volume }}"></label>
                    @include('story-studio.partials.audio', ['asset' => $effect->audioAssets->first(), 'empty' => ''])
                    @include('story-studio.partials.state', ['state' => $effect->status])
                    <button class="btn btn-sm">ذخیره</button>
                    <button class="btn btn-sm btn-ghost" formaction="{{ route('story-studio.projects.effects.regenerate', [$project, $effect]) }}" name="_method" value="POST">↻</button>
                    <button class="btn btn-sm btn-ghost btn-danger" formaction="{{ route('story-studio.projects.effects.destroy', [$project, $effect]) }}" name="_method" value="DELETE">✕</button>
                </form>
            @endforeach

            <form method="post" action="{{ route('story-studio.projects.effects.store', [$project, $scene]) }}" class="effect-row add">
                @csrf
                <select class="select" name="kind">@foreach (\App\Enums\StoryStudio\SoundKind::cases() as $kind)<option value="{{ $kind->value }}">{{ $kind->label() }}</option>@endforeach</select>
                <input class="input" name="description" placeholder="مثلاً: آواز پرندگان" required>
                <input class="input ltr-input grow-2" name="prompt" placeholder="gentle birds chirping in a forest">
                <input type="hidden" name="volume" value="0.6">
                <input type="hidden" name="enabled" value="1">
                <button class="btn btn-sm btn-dark">＋ افکت</button>
            </form>
        </section>
    @endforeach
@endsection

@section('inspector')
    <section class="inspector-section">
        <h3>میکس و خروجی</h3>
        <form method="post" action="{{ route('story-studio.projects.settings.update', $project) }}" class="stack-form">
            @csrf @method('PUT')
            <label>بلندی موسیقی <input class="input ltr-input" type="number" step="0.05" min="0" max="1.5" name="music_volume" value="{{ $settings->music_volume }}"></label>
            <label>کاهش موسیقی هنگام صحبت (dB) <input class="input ltr-input" type="number" min="0" max="30" name="ducking_db" value="{{ $settings->ducking_db }}"></label>
            <label>ترنزیشن پیش‌فرض
                <select class="select" name="default_transition">@foreach (\App\Enums\StoryStudio\Transition::cases() as $t)<option value="{{ $t->value }}" @selected($settings->default_transition === $t)>{{ $t->label() }}</option>@endforeach</select>
            </label>
            <label>مدت ترنزیشن (ms) <input class="input ltr-input" type="number" min="0" max="3000" step="50" name="transition_duration_ms" value="{{ $settings->transition_duration_ms }}"></label>
            <label>حرکت پیش‌فرض
                <select class="select" name="default_motion">@foreach (\App\Enums\StoryStudio\ImageMotion::cases() as $m)<option value="{{ $m->value }}" @selected($settings->default_motion === $m)>{{ $m->label() }}</option>@endforeach</select>
            </label>
            <label>زیرنویس
                <select class="select" name="subtitle_mode">@foreach (\App\Enums\StoryStudio\SubtitleMode::cases() as $m)<option value="{{ $m->value }}" @selected($settings->subtitle_mode === $m)>{{ $m->label() }}</option>@endforeach</select>
            </label>
            <label>اندازه زیرنویس <input class="input ltr-input" type="number" min="24" max="96" name="subtitle_font_size" value="{{ $settings->subtitle_font_size }}"></label>
            <label class="check"><input type="checkbox" name="burn_subtitles" value="1" @checked($settings->burn_subtitles)> زیرنویس روی ویدیو سوزانده شود</label>
            <label class="check"><input type="checkbox" name="show_title_on_cover" value="1" @checked($settings->show_title_on_cover)> عنوان روی جلد نوشته شود</label>
            <button class="btn btn-sm btn-primary">ذخیره تنظیمات</button>
        </form>
    </section>
@endsection
