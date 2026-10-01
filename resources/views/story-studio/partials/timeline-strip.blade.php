@use('App\Support\StoryStudio\Fa')
@php($strip = app(\App\Services\StoryStudio\TimelineService::class)->build($project))
<section class="studio-timeline" aria-label="تایم‌لاین">
    <div class="timeline-head">
        <strong>تایم‌لاین</strong>
        <span>{{ Fa::digits(count($strip['scenes'])) }} صحنه · {{ Fa::duration($strip['total_ms']) }}</span>
        <a class="timeline-open" href="{{ route('story-studio.projects.timeline', $project) }}">ویرایش تایم‌لاین ←</a>
    </div>
    @if ($strip['scenes'])
        <div class="strip ltr-track">
            @foreach ($strip['scenes'] as $scene)
                <a class="strip-scene" style="flex: {{ max(1, $scene['duration_ms']) }}" href="{{ route('story-studio.projects.timeline', [$project, 'scene' => $scene['id']]) }}" title="صحنه {{ $scene['number'] }}">
                    @if ($scene['image_id'])
                        <img src="{{ route('story-studio.files.show', ['asset', $scene['image_id']]) }}" alt="" loading="lazy">
                    @endif
                    <span>{{ Fa::digits($scene['number']) }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="tracks">
            @foreach (['IMAGE / VISUAL', 'NARRATOR', 'CHARACTERS', 'SFX', 'AMBIENCE', 'MUSIC', 'SUBTITLES'] as $track)
                <span class="track-name">{{ $track }}</span><span class="track-lane"></span>
            @endforeach
        </div>
    @endif
</section>
