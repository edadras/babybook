@extends('story-studio.layouts.studio')
@use('App\Enums\StoryStudio\ProjectStatus')
@use('App\Support\StoryStudio\Fa')

@section('title', 'استودیو داستان')

@section('topbar-actions')
    <a href="{{ route('story-studio.projects.create') }}" class="btn btn-primary">＋ داستان جدید</a>
@endsection

@section('body')
<main class="page">
    @include('story-studio.partials.flash')

    <div class="page-head">
        <div>
            <h1>استودیو داستان</h1>
            <p class="lead">داستان را بنویسید؛ کارگردان هوشمند آن را به کتاب صوتی و ویدیوی مصور تبدیل می‌کند.</p>
        </div>
    </div>

    <form class="toolbar" method="get" action="{{ route('story-studio.index') }}">
        <nav class="chips" aria-label="فیلتر وضعیت">
            <a href="{{ route('story-studio.index', array_filter(['q' => $search])) }}" @class(['chip', 'active' => ! $statusFilter])>همه</a>
            @foreach ([ProjectStatus::Draft, ProjectStatus::ReadyForReview, ProjectStatus::Completed, ProjectStatus::Failed] as $status)
                <a href="{{ route('story-studio.index', array_filter(['status' => $status->value, 'q' => $search])) }}" @class(['chip', 'active' => $statusFilter === $status])>{{ $status->label() }}</a>
            @endforeach
        </nav>
        @if ($statusFilter)
            <input type="hidden" name="status" value="{{ $statusFilter->value }}">
        @endif
        <label class="sr-only" for="q">جستجو</label>
        <input class="search" type="search" id="q" name="q" value="{{ $search }}" placeholder="جستجوی عنوان داستان…">
    </form>

    @if ($projects->isEmpty())
        <section class="empty">
            <div class="art" aria-hidden="true">📖</div>
            @if ($search || $statusFilter)
                <h2>پروژه‌ای با این فیلتر پیدا نشد</h2>
                <p>فیلترها را تغییر دهید یا یک داستان تازه شروع کنید.</p>
            @else
                <h2>اولین داستانتان را بسازید</h2>
                <p>متن یک قصه کودکانه را وارد کنید تا شخصیت‌ها، صحنه‌ها، تصاویر و صداها ساخته شوند.</p>
            @endif
            <a href="{{ route('story-studio.projects.create') }}" class="btn btn-primary">＋ داستان جدید</a>
        </section>
    @else
        <section class="project-grid">
            @foreach ($projects as $project)
                <article class="project-card">
                    <a href="{{ route('story-studio.projects.show', $project) }}" aria-label="{{ $project->title }}">
                        @include('story-studio.partials.cover', ['project' => $project])
                    </a>
                    <div class="card-body">
                        <h2 class="card-title"><a href="{{ route('story-studio.projects.show', $project) }}">{{ $project->title }}</a></h2>
                        <div class="card-meta">
                            <div class="meta-cell"><span>زبان</span><strong>{{ Fa::language($project->language) }}</strong></div>
                            <div class="meta-cell"><span>صحنه‌ها</span><strong>{{ Fa::digits($project->scenes_count) }}</strong></div>
                            <div class="meta-cell"><span>مدت</span><strong>{{ Fa::duration($project->duration_ms) }}</strong></div>
                        </div>
                        <div class="progress-row">
                            <div class="progress" role="progressbar" aria-valuenow="{{ $project->progress }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ $project->progress }}%"></i></div>
                            <span>{{ Fa::digits($project->progress) }}٪</span>
                        </div>
                        <div class="dates">
                            <span title="تاریخ ایجاد">ایجاد: {{ Fa::date($project->created_at) }}</span>
                            <span title="آخرین تغییر">ویرایش: {{ Fa::date($project->updated_at) }}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a href="{{ route('story-studio.projects.show', $project) }}" class="btn btn-primary btn-sm">ادامه پروژه</a>
                        <a href="{{ route('story-studio.projects.edit', $project) }}" class="btn btn-sm">ویرایش</a>
                        @php($hasFinal = $project->work_state['final'] ?? null)
                        <a @class(['btn btn-sm btn-ghost', 'is-disabled' => ! $project->scenes_count]) href="{{ route($hasFinal ? 'story-studio.projects.export' : 'story-studio.projects.storyboard', $project) }}">پیش‌نمایش</a>
                        <a @class(['btn btn-sm btn-ghost', 'is-disabled' => ! $project->scenes_count]) href="{{ route('story-studio.projects.render', $project) }}">رندر نهایی</a>
                        <a @class(['btn btn-sm btn-ghost', 'is-disabled' => ! $hasFinal]) href="{{ route('story-studio.projects.export', $project) }}">دانلود</a>
                    </div>
                </article>
            @endforeach
        </section>

        @if ($projects->hasPages())
            <nav class="pagination" aria-label="صفحه‌بندی">
                @if (! $projects->onFirstPage())
                    <a class="btn btn-sm" href="{{ $projects->previousPageUrl() }}">→ قبلی</a>
                @endif
                <span class="btn btn-sm btn-ghost">صفحه {{ Fa::digits($projects->currentPage()) }} از {{ Fa::digits($projects->lastPage()) }}</span>
                @if ($projects->hasMorePages())
                    <a class="btn btn-sm" href="{{ $projects->nextPageUrl() }}">بعدی ←</a>
                @endif
            </nav>
        @endif
    @endif
</main>
@endsection
