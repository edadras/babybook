@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'export')

@php
    $video = $outputs['final_video'];
    $audio = $outputs['final_audio'];
    $cover = $outputs['cover'];
    $file = fn ($asset, $download = false) => route('story-studio.files.show', ['asset', $asset->id] + ($download ? ['download' => 1] : []));
@endphp

@section('canvas')
    <div class="canvas-head"><h1>خروجی نهایی</h1></div>

    @if (! $video)
        <div class="empty small">
            <div class="art">🎬</div>
            <p>هنوز رندر نهایی انجام نشده است. از پنل وضعیت «ساخت کامل داستان» را بزنید.</p>
        </div>
    @else
        @if ($project->workState('final')->value === 'stale')
            <div class="alert warn">پس از آخرین رندر تغییراتی داده شده است. برای به‌روزرسانی خروجی، دوباره رندر کنید.</div>
        @endif
        <section class="final-player panel">
            <h2 class="panel-title">▶ مشاهده ویدیو</h2>
            <video controls preload="metadata" poster="{{ $cover ? $file($cover) : '' }}" src="{{ $file($video) }}">
                @if ($outputs['subtitle_vtt'])<track kind="subtitles" srclang="fa" label="فارسی" src="{{ $file($outputs['subtitle_vtt']) }}" default>@endif
            </video>
        </section>
        @if ($audio)
            <section class="panel">
                <h2 class="panel-title">🎧 شنیدن کتاب صوتی</h2>
                <audio class="audio wide" controls preload="none" src="{{ $file($audio) }}"></audio>
            </section>
        @endif

        <section class="panel">
            <h2 class="panel-title">دانلود</h2>
            <div class="downloads">
                @foreach (['final_video' => ['MP4', '🎬'], 'final_audio' => ['MP3', '🎧'], 'subtitle_srt' => ['SRT', '💬'], 'subtitle_vtt' => ['VTT', '💬'], 'cover' => ['جلد', '🖼'], 'assets_zip' => ['ZIP فایل‌ها', '📦']] as $key => [$label, $icon])
                    @if ($outputs[$key])
                        <a class="download" href="{{ $file($outputs[$key], true) }}">
                            <span class="icon">{{ $icon }}</span>
                            <strong>{{ $label }}</strong>
                            <span class="subtle">{{ Fa::number(round(($outputs[$key]->file_size ?? 0) / 1048576, 1), 1) }} MB · v{{ $outputs[$key]->version }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
@endsection

@section('inspector')
    <section class="inspector-section">
        <h3>مشخصات</h3>
        @if ($cover)<img class="cover-thumb" src="{{ $file($cover) }}" alt="جلد">@endif
        <div class="kv"><span>مدت</span><strong>{{ Fa::duration($video?->duration_ms ?? $project->duration_ms) }}</strong></div>
        <div class="kv"><span>صحنه‌ها</span><strong>{{ Fa::digits($project->scenes_count) }}</strong></div>
        <div class="kv"><span>شخصیت‌ها</span><strong>{{ Fa::digits($project->characters_count) }}</strong></div>
        <div class="kv"><span>کیفیت</span><strong class="ltr">{{ $video ? $video->width.'×'.$video->height : implode('×', $project->frameSize()) }}</strong></div>
        <div class="kv"><span>هزینه ساخت</span><strong class="ltr">{{ Fa::money($costs['total_actual']) }}</strong></div>
        <div class="kv"><span>تاریخ ساخت</span><strong>{{ Fa::date($video?->created_at, true) }}</strong></div>
    </section>
@endsection
