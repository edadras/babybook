@extends('story-studio.layouts.studio')
@use('App\Support\StoryStudio\Fa')

{{-- Studio workspace: nav (left), canvas (center), inspector (right), timeline strip (bottom). --}}

@section('title', $project->title)
@section('crumb', $project->title)

@section('topbar-actions')
    <a href="{{ route('story-studio.projects.edit', $project) }}" class="btn btn-sm btn-ghost on-dark">ویرایش اطلاعات</a>
    <a href="{{ route('story-studio.projects.export', $project) }}" class="btn btn-sm btn-primary">خروجی</a>
@endsection

@php
    $tab = trim($__env->yieldContent('tab')) ?: 'story';
    $nav = [
        ['key' => 'story', 'label' => 'داستان', 'icon' => '📖', 'route' => 'story-studio.projects.show'],
        ['key' => 'characters', 'label' => 'شخصیت‌ها', 'icon' => '🧸', 'route' => 'story-studio.projects.characters'],
        ['key' => 'voices', 'label' => 'صداها', 'icon' => '🎙', 'route' => 'story-studio.projects.voices'],
        ['key' => 'pronunciation', 'label' => 'تلفظ', 'icon' => 'آ', 'route' => 'story-studio.projects.pronunciation'],
        ['key' => 'storyboard', 'label' => 'استوری‌بورد', 'icon' => '🎞', 'route' => 'story-studio.projects.storyboard'],
        ['key' => 'sound', 'label' => 'صداگذاری', 'icon' => '🎧', 'route' => 'story-studio.projects.sound'],
        ['key' => 'timeline', 'label' => 'تایم‌لاین', 'icon' => '⏱', 'route' => 'story-studio.projects.timeline'],
        ['key' => 'render', 'label' => 'رندر', 'icon' => '⚙', 'route' => 'story-studio.projects.render'],
        ['key' => 'export', 'label' => 'خروجی', 'icon' => '⬇', 'route' => 'story-studio.projects.export'],
        ['key' => 'costs', 'label' => 'هزینه‌ها', 'icon' => '$', 'route' => 'story-studio.projects.costs'],
    ];
@endphp

@section('body')
<div class="studio">
    <nav class="studio-nav" aria-label="بخش‌های پروژه">
        <div class="project-chip">
            <strong>{{ $project->title }}</strong>
            <span>{{ $project->mode->value === 'auto' ? 'حالت خودکار' : 'استودیو دستی' }} · {{ $project->aspect_ratio->value }}</span>
        </div>
        @foreach ($nav as $item)
            <a href="{{ route($item['route'], $project) }}" @class(['nav-item', 'active' => $item['key'] === $tab])>
                <span class="icon" aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <main class="studio-canvas">
        @include('story-studio.partials.flash')
        @yield('canvas')
    </main>

    <aside class="studio-inspector" aria-label="بازرس">
        @include('story-studio.partials.pipeline-panel', ['project' => $project])
        @yield('inspector')
    </aside>

    @unless ($tab === 'timeline')
        @include('story-studio.partials.timeline-strip', ['project' => $project])
    @endunless
</div>
@endsection
