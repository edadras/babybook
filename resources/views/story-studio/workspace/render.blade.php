@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'render')

@section('canvas')
    <div class="canvas-head">
        <h1>رندر</h1>
        <span class="muted">فقط بخش‌های تغییرکرده دوباره ساخته می‌شوند.</span>
        <span class="grow"></span>
        @if ($project->mode->value === 'manual' && ($report['preview']['status'] ?? '') === 'awaiting_approval')
            <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, 'review']) }}">@csrf<button class="btn btn-primary">✓ بازبینی شد — رندر نهایی</button></form>
        @endif
    </div>

    <section class="panel">
        <h2 class="panel-title">آمادگی برای رندر نهایی</h2>
        <ul class="checklist">
            @foreach (\App\Enums\StoryStudio\PipelineStage::cases() as $stage)
                @php($row = $report[$stage->value])
                <li class="st-{{ $row['status'] }}"><span class="dot">{{ $row['status'] === 'done' ? '✓' : '' }}</span>{{ $stage->label() }}<span class="count">{{ $row['total'] ? Fa::digits($row['done']).'/'.Fa::digits($row['total']) : '' }}</span></li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">پیش‌نمایش صحنه‌ها</h2>
        <div class="clip-grid">
            @foreach ($scenes as $scene)
                @php($clip = $scene->assets->first())
                <figure class="clip">
                    @if ($clip)
                        <video controls preload="none" src="{{ route('story-studio.files.show', ['asset', $clip->id]) }}"></video>
                    @else
                        <div class="placeholder">{{ $scene->workState('video')->label() }}</div>
                    @endif
                    <figcaption>صحنه {{ Fa::digits($scene->scene_number) }} @include('story-studio.partials.state', ['state' => $scene->workState('video')])</figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">تاریخچه رندر</h2>
        <table class="table">
            <thead><tr><th>زمان</th><th>نوع</th><th>وضعیت</th><th>مدت</th><th>خطا</th></tr></thead>
            @forelse ($renders as $render)
                <tr>
                    <td>{{ Fa::date($render->created_at, true) }}</td>
                    <td>{{ $render->scope->label() }}</td>
                    <td>{{ $render->status->label() }}</td>
                    <td>{{ $render->started_at && $render->finished_at ? Fa::digits($render->started_at->diffInSeconds($render->finished_at)).' ثانیه' : '—' }}</td>
                    <td class="subtle">{{ \Illuminate\Support\Str::limit($render->error_message, 80) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="subtle">هنوز رندری انجام نشده است.</td></tr>
            @endforelse
        </table>
    </section>
@endsection
