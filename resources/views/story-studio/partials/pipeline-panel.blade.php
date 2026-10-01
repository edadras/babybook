@use('App\Support\StoryStudio\Fa')
@php
    $progress = app(\App\Services\StoryStudio\ProjectProgressService::class)->summarize($project);
    $estimate = app(\App\Services\StoryStudio\CostTracker::class)->estimate($project);
    $gate = $progress['gate'];
    $hasFailed = collect($progress['stages'])->contains('state', 'failed');
@endphp
<section class="inspector-section pipeline-panel" data-progress-url="{{ route('story-studio.projects.progress', $project) }}" data-working="{{ $progress['working'] ? '1' : '0' }}" data-signature="{{ $progress['signature'] }}">
    <h3>{{ $project->status->value === 'completed' ? 'کتاب ساخته شد ✓' : ($progress['running'] ? 'در حال ساخت کتاب…' : 'وضعیت ساخت') }}</h3>
    <div class="big-progress">
        <strong data-progress-value>{{ Fa::digits($progress['progress']) }}٪</strong>
        <span class="pill tone-{{ $progress['status_tone'] }}" data-status-pill>{{ $progress['paused'] ? 'متوقف' : $progress['status_label'] }}</span>
    </div>
    <div class="progress" style="margin-bottom: 12px"><i data-progress-bar style="width: {{ $progress['progress'] }}%"></i></div>

    <ul class="stage-list">
        @foreach ($progress['stages'] as $stage)
            <li class="st-{{ $stage['state'] }}" data-stage="{{ $stage['key'] }}">
                <span class="dot" aria-hidden="true">{{ $stage['state'] === 'done' ? '✓' : ($stage['state'] === 'failed' ? '!' : '') }}</span>
                {{ $stage['label'] }}
                <span class="count">{{ $stage['total'] ? Fa::digits($stage['done']).'/'.Fa::digits($stage['total']) : '' }}</span>
            </li>
        @endforeach
    </ul>

    <div class="toast" data-refresh-toast hidden>
        <span>نتایج تازه آماده است.</span>
        <button type="button" class="btn btn-sm btn-dark" data-refresh-now>به‌روزرسانی</button>
    </div>

    @if ($project->last_error)
        <div class="alert danger"><strong>خطا:</strong> {{ \Illuminate\Support\Str::limit($project->last_error, 300) }}</div>
    @endif

    @if ($gate)
        <div class="alert info">
            <strong>منتظر تأیید شما:</strong> {{ Fa::gateLabel($gate) }}
            @php
                $gateLink = match ($gate) {
                    'characters' => route('story-studio.projects.characters', $project),
                    'voices' => route('story-studio.projects.voices', $project),
                    'storyboard' => route('story-studio.projects.storyboard', $project),
                    'review' => route('story-studio.projects.render', $project),
                    default => route('story-studio.projects.show', $project),
                };
            @endphp
            <div class="row-actions">
                <a href="{{ $gateLink }}" class="btn btn-sm">بازبینی</a>
                <form method="post" action="{{ route('story-studio.projects.pipeline.approve', [$project, $gate]) }}">
                    @csrf
                    <button class="btn btn-sm btn-primary">تأیید و ادامه</button>
                </form>
            </div>
        </div>
    @endif

    <div class="pipeline-actions">
        @if ($progress['paused'])
            <form method="post" action="{{ route('story-studio.projects.pipeline.resume', $project) }}">@csrf<button class="btn btn-sm btn-primary">▶ ادامه</button></form>
        @elseif ($progress['running'] && ! in_array($project->status->value, ['completed'], true))
            <form method="post" action="{{ route('story-studio.projects.pipeline.pause', $project) }}">@csrf<button class="btn btn-sm">⏸ توقف</button></form>
        @endif
        @if ($hasFailed || $project->last_error)
            <form method="post" action="{{ route('story-studio.projects.pipeline.retry', $project) }}">@csrf<button class="btn btn-sm btn-dark">↻ تلاش مجدد</button></form>
        @endif
        @if ($progress['running'] && $project->status->value !== 'completed')
            <form method="post" action="{{ route('story-studio.projects.pipeline.cancel', $project) }}" data-confirm="ساخت لغو شود؟ کارهای تکمیل‌شده حفظ می‌شوند.">@csrf<button class="btn btn-sm btn-ghost btn-danger">لغو</button></form>
        @endif
    </div>

    @if (! $progress['running'] || $project->status->value === 'completed')
        <details class="cost-confirm" @if (session('open_estimate')) open @endif>
            <summary class="btn btn-primary btn-block">{{ $project->status->value === 'completed' ? '↻ ساخت دوباره بخش‌های تغییرکرده' : ($project->scenes()->exists() ? '▶ ادامه ساخت کامل داستان' : '▶ ساخت کامل داستان') }}</summary>
            <form method="post" action="{{ route('story-studio.projects.pipeline.start', $project) }}" class="estimate">
                @csrf
                <p class="subtle">هزینه تخمینی بخش‌هایی که هنوز ساخته نشده‌اند (کارهای تکمیل‌شده دوباره ساخته نمی‌شوند):</p>
                @if (! $estimate['lines'])<p class="subtle">✓ هیچ تولید پولی لازم نیست؛ فقط میکس و رندر انجام می‌شود.</p>@endif
                <table class="mini-table">
                    @foreach ($estimate['lines'] as $line)
                        <tr><td>{{ $line['label'] }}</td><td class="ltr">{{ Fa::number($line['units']) }}</td><td class="ltr">{{ Fa::money($line['cost']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>جمع حداکثر</td><td></td><td class="ltr">{{ Fa::money($estimate['total']) }}</td></tr>
                </table>
                <label class="check"><input type="checkbox" name="confirm_cost" value="1" required> هزینه تخمینی را تأیید می‌کنم</label>
                <button class="btn btn-primary btn-block">شروع</button>
            </form>
        </details>
    @endif
</section>
