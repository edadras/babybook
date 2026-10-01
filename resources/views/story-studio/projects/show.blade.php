@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'story')

@php
    $meta = $project->story_metadata ?? [];
    $safety = $project->safety_report;
    $flagLabels = ['violence' => 'خشونت', 'sexual_content' => 'محتوای جنسی', 'extreme_fear' => 'ترس شدید', 'self_harm' => 'آسیب به خود', 'dangerous_imitation' => 'تقلید خطرناک', 'hate' => 'نفرت', 'adult_themes' => 'مضامین بزرگسالانه', 'other' => 'سایر'];
    $severity = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];
@endphp

@section('canvas')
    <div class="canvas-head">
        <h1>داستان</h1>
        <span class="tag">{{ Fa::language($project->language) }}</span>
        <span class="tag">{{ $project->category->label() }}</span>
        <span class="tag">{{ $project->target_age->label() }}</span>
        <span class="grow"></span>
        @if ($project->scenes_count)
            <form method="post" action="{{ route('story-studio.projects.pipeline.analyze', $project) }}" data-confirm="تحلیل دوباره، فیلمنامه و صحنه‌ها را بازسازی می‌کند (شخصیت‌های قفل‌شده و فایل‌ها حفظ می‌شوند). ادامه؟">
                @csrf <button class="btn btn-sm">↻ تحلیل دوباره</button>
            </form>
        @endif
    </div>

    @if ($safety && ($safety['flags'] ?? []) !== [])
        <section @class(['panel', 'safety', 'acknowledged' => $project->safety_acknowledged_at])>
            <header>
                <h2>⚠ بررسی ایمنی محتوای کودک</h2>
                <p class="muted">هوش مصنوعی متن را تغییر نداده است؛ موارد زیر فقط برای تصمیم شما علامت خورده‌اند.</p>
            </header>
            <ul class="flags">
                @foreach ($safety['flags'] as $flag)
                    <li class="sev-{{ $flag['severity'] }}">
                        <strong>{{ $flagLabels[$flag['category']] ?? $flag['category'] }}</strong>
                        <span class="tag">شدت: {{ $severity[$flag['severity']] ?? $flag['severity'] }}</span>
                        <blockquote>«{{ $flag['excerpt'] }}»</blockquote>
                        <p class="subtle ltr-text">{{ $flag['note'] }}</p>
                    </li>
                @endforeach
            </ul>
            @if (! $project->safety_acknowledged_at)
                <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, 'safety']) }}">
                    @csrf <button class="btn btn-primary btn-sm">بررسی کردم؛ ادامه تولید</button>
                    <a class="btn btn-sm" href="{{ route('story-studio.projects.edit', $project) }}">ویرایش متن داستان</a>
                </form>
            @else
                <p class="subtle">✓ بررسی شده در {{ Fa::date($project->safety_acknowledged_at, true) }}</p>
            @endif
        </section>
    @endif

    @if ($meta)
        <section class="panel analysis">
            <div class="analysis-grid">
                <div><span>خلاصه</span><p>{{ $meta['summary'] ?? '—' }}</p></div>
                <div><span>پیام داستان</span><p class="ltr-text">{{ $meta['moral'] ?? '—' }}</p></div>
                <div><span>لحن</span><p class="ltr-text">{{ $meta['tone'] ?? '—' }}</p></div>
                <div><span>مدت تخمینی</span><p>{{ Fa::duration(($meta['estimated_duration_seconds'] ?? 0) * 1000) }}</p></div>
            </div>
            <div class="analysis-counts">
                <a href="{{ route('story-studio.projects.storyboard', $project) }}"><strong>{{ Fa::digits($project->scenes_count) }}</strong> صحنه</a>
                <a href="{{ route('story-studio.projects.characters', $project) }}"><strong>{{ Fa::digits($project->characters_count) }}</strong> شخصیت</a>
                <span><strong>{{ Fa::digits($project->locations_count) }}</strong> مکان</span>
                <span class="subtle">کارگردان: {{ $meta['director_model'] ?? '—' }}</span>
            </div>
            @if (! empty($meta['warnings']))
                <details class="warnings">
                    <summary>⚠ {{ Fa::digits(count($meta['warnings'])) }} مورد عدم انطباق روایت با متن اصلی</summary>
                    <ul>@foreach ($meta['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                </details>
            @endif
            @if ($project->mode->value === 'manual' && ! $project->script_approved_at)
                <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, 'script']) }}" class="approve-bar">
                    @csrf
                    <span>فیلمنامه را در <a href="{{ route('story-studio.projects.storyboard', $project) }}">استوری‌بورد</a> بررسی کنید، سپس تأیید کنید.</span>
                    <button class="btn btn-primary btn-sm">✓ تأیید فیلمنامه</button>
                </form>
            @endif
        </section>
    @elseif ($project->workState('analysis')->value === 'failed')
        <div class="alert danger">تحلیل داستان ناموفق بود. از پنل وضعیت «تلاش مجدد» را بزنید.</div>
    @elseif (in_array($project->workState('analysis')->value, ['queued', 'generating'], true))
        <div class="alert info">کارگردان هوشمند در حال خواندن و کارگردانی داستان است…</div>
    @else
        <section class="panel hint-panel">
            <h2>آماده کارگردانی</h2>
            <p class="muted">با «ساخت کامل داستان» در پنل سمت راست، کارگردان هوشمند داستان را تحلیل می‌کند: شخصیت‌ها، مکان‌ها، صحنه‌ها، دیالوگ‌ها، احساسات، قاب‌بندی دوربین و طراحی صدا.
                {{ $project->mode->value === 'manual' ? 'در حالت دستی پس از هر مرحله برای تأیید شما متوقف می‌شود.' : 'در حالت خودکار همه مراحل پشت سر هم انجام می‌شود.' }}</p>
        </section>
    @endif

    <article class="manuscript" lang="{{ $project->language }}" dir="{{ $project->language === 'fa' ? 'rtl' : 'ltr' }}">
        <div class="manuscript-head">
            <span>متن اصلی نویسنده</span><span>·</span><span>{{ Fa::number(mb_strlen($project->original_text)) }} نویسه</span>
            <span>·</span><span>بدون تغییر نگه داشته می‌شود</span>
        </div>
        <div class="manuscript-text">{{ $project->original_text }}</div>
    </article>
@endsection

@section('inspector')
    <section class="inspector-section">
        <h3>تنظیمات پروژه</h3>
        <div class="kv"><span>سبک تصویری</span><strong>{{ $project->visual_style->label() }}</strong></div>
        <div class="kv"><span>سبک روایت</span><strong>{{ $project->narration_style->label() }}</strong></div>
        <div class="kv"><span>قاب</span><strong class="ltr">{{ $project->aspect_ratio->value }} · {{ implode('×', $project->frameSize()) }}</strong></div>
        <div class="kv"><span>حالت ساخت</span><strong>{{ $project->mode->value === 'auto' ? 'خودکار' : 'دستی' }}</strong></div>
        <div class="kv"><span>ایجاد</span><strong>{{ Fa::date($project->created_at) }}</strong></div>
        <div class="kv"><span>آخرین تغییر</span><strong>{{ Fa::date($project->updated_at, true) }}</strong></div>
        @if ($project->custom_visual_instructions)
            <p class="subtle" style="margin-top:8px">{{ $project->custom_visual_instructions }}</p>
        @endif
    </section>
    <section class="inspector-section">
        <h3>مدیریت</h3>
        <form method="post" action="{{ route('story-studio.projects.destroy', $project) }}" data-confirm="پروژه «{{ $project->title }}» حذف شود؟ فایل‌های ساخته‌شده حفظ می‌شوند.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger btn-block">حذف پروژه</button>
        </form>
    </section>
@endsection
