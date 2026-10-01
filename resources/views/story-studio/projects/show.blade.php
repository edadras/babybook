@extends('story-studio.layouts.studio')
@use('App\Support\StoryStudio\Fa')

@section('title', $project->title)
@section('crumb', $project->title)

@section('topbar-actions')
    <a href="{{ route('story-studio.projects.edit', $project) }}" class="btn btn-sm btn-ghost" style="color: var(--night-text)">ویرایش</a>
    <button type="button" class="btn btn-sm btn-primary" disabled title="در فاز ۲ (تحلیل داستان) فعال می‌شود">▶ شروع کارگردانی</button>
@endsection

@php
    $nav = [
        ['key' => 'story', 'label' => 'داستان', 'icon' => '📖', 'ready' => true],
        ['key' => 'characters', 'label' => 'شخصیت‌ها', 'icon' => '🧸', 'ready' => false],
        ['key' => 'voices', 'label' => 'صداها', 'icon' => '🎙', 'ready' => false],
        ['key' => 'storyboard', 'label' => 'استوری‌بورد', 'icon' => '🎞', 'ready' => false],
        ['key' => 'sound', 'label' => 'صداگذاری', 'icon' => '🎧', 'ready' => false],
        ['key' => 'timeline', 'label' => 'تایم‌لاین', 'icon' => '⏱', 'ready' => false],
        ['key' => 'render', 'label' => 'رندر', 'icon' => '⚙', 'ready' => false],
        ['key' => 'export', 'label' => 'خروجی', 'icon' => '⬇', 'ready' => false],
    ];
    $frame = $project->frameSize();
@endphp

@section('body')
<div class="studio">
    <nav class="studio-nav" aria-label="بخش‌های پروژه">
        <div class="project-chip">
            <strong>{{ $project->title }}</strong>
            <span>{{ $project->mode->label() }}</span>
        </div>
        @foreach ($nav as $item)
            @if ($item['ready'])
                <a href="{{ route('story-studio.projects.show', $project) }}" @class(['nav-item', 'active' => $item['key'] === 'story'])>
                    <span class="icon" aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}
                </a>
            @else
                <span class="nav-item locked" aria-disabled="true">
                    <span class="icon" aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}<span class="soon">به‌زودی</span>
                </span>
            @endif
        @endforeach
    </nav>

    <main class="studio-canvas">
        @include('story-studio.partials.flash')
        <div class="canvas-head">
            <h1>متن داستان</h1>
            <span class="tag">{{ Fa::language($project->language) }}</span>
            <span class="tag">{{ $project->category->label() }}</span>
            <span class="tag">{{ $project->target_age->label() }}</span>
        </div>
        <article class="manuscript" lang="{{ $project->language }}" dir="{{ $project->language === 'fa' ? 'rtl' : 'ltr' }}">
            <div class="manuscript-head">
                <span>متن اصلی نویسنده</span><span>·</span><span>{{ Fa::number(mb_strlen($project->original_text)) }} نویسه</span>
                <span>·</span><span>بدون تغییر نگه داشته می‌شود</span>
            </div>
            <div class="manuscript-text">{{ $project->original_text }}</div>
        </article>

        @if ($project->custom_visual_instructions)
            <section class="panel" style="padding: 16px 20px; max-width: 780px">
                <h3 style="font-size: 14px; margin-bottom: 6px">دستورالعمل تصویری اختصاصی</h3>
                <p class="muted">{{ $project->custom_visual_instructions }}</p>
            </section>
        @endif
    </main>

    <aside class="studio-inspector" aria-label="بازرس پروژه">
        <section class="inspector-section" data-progress-url="{{ route('story-studio.projects.progress', $project) }}" data-working="{{ $progress['working'] ? '1' : '0' }}">
            <h3>وضعیت ساخت</h3>
            <div class="big-progress">
                <strong data-progress-value>{{ Fa::digits($progress['progress']) }}٪</strong>
                <span class="pill tone-{{ $progress['status_tone'] }}" data-status-pill>{{ $progress['status_label'] }}</span>
            </div>
            <div class="progress" style="margin-bottom: 14px"><i data-progress-bar style="width: {{ $progress['progress'] }}%"></i></div>
            <ul class="stage-list">
                @foreach ($progress['stages'] as $stage)
                    <li class="st-{{ $stage['state'] }}" data-stage="{{ $stage['key'] }}">
                        <span class="dot" aria-hidden="true">{{ $stage['state'] === 'done' ? '✓' : '' }}</span>
                        {{ $stage['label'] }}
                        <span class="count">{{ $stage['total'] ? Fa::digits($stage['done']).'/'.Fa::digits($stage['total']) : '' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="inspector-section">
            <h3>تنظیمات پروژه</h3>
            <div class="kv"><span>سبک تصویری</span><strong>{{ $project->visual_style->label() }}</strong></div>
            <div class="kv"><span>سبک روایت</span><strong>{{ $project->narration_style->label() }}</strong></div>
            <div class="kv"><span>قاب</span><strong class="ltr">{{ $project->aspect_ratio->value }} · {{ $frame[0] }}×{{ $frame[1] }}</strong></div>
            <div class="kv"><span>حالت ساخت</span><strong>{{ $project->mode->value === 'auto' ? 'خودکار' : 'دستی' }}</strong></div>
            <div class="kv"><span>صحنه‌ها</span><strong>{{ Fa::digits($project->scenes_count) }}</strong></div>
            <div class="kv"><span>شخصیت‌ها</span><strong>{{ Fa::digits($project->characters_count) }}</strong></div>
            <div class="kv"><span>ایجاد</span><strong>{{ Fa::date($project->created_at) }}</strong></div>
            <div class="kv"><span>آخرین تغییر</span><strong>{{ Fa::date($project->updated_at, true) }}</strong></div>
            <a href="{{ route('story-studio.projects.edit', $project) }}" class="btn btn-sm" style="width: 100%; margin-top: 10px">ویرایش اطلاعات پروژه</a>
        </section>

        <section class="inspector-section">
            <h3>مدیریت</h3>
            <form method="post" action="{{ route('story-studio.projects.destroy', $project) }}" data-confirm="پروژه «{{ $project->title }}» حذف شود؟ فایل‌های ساخته‌شده حفظ می‌شوند.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" style="width: 100%">حذف پروژه</button>
            </form>
        </section>
    </aside>

    <section class="studio-timeline" aria-label="تایم‌لاین">
        <div class="timeline-head">
            <strong>تایم‌لاین</strong>
            <span>پس از ساخت صحنه‌ها و صداها، ترک‌ها اینجا نمایش داده می‌شوند.</span>
        </div>
        <div class="tracks">
            @foreach (['IMAGE / VISUAL', 'NARRATOR', 'CHARACTERS', 'SFX', 'AMBIENCE', 'MUSIC', 'SUBTITLES'] as $track)
                <span class="track-name">{{ $track }}</span><span class="track-lane"></span>
            @endforeach
        </div>
    </section>
</div>
@endsection
