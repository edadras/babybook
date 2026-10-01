{{-- Project cover, or a deterministic gradient placeholder with the title's first letter. --}}
<div class="cover">
    @if ($project->cover?->file_path)
        <img src="{{ route('story-studio.files.show', ['asset', $project->cover->id]) }}" alt="جلد {{ $project->title }}" loading="lazy">
    @else
        <div class="cover-placeholder hue-{{ crc32($project->id) % 6 }}" aria-hidden="true">{{ mb_substr($project->title, 0, 1) }}</div>
    @endif
    <span class="pill tone-{{ $project->status->tone() }}">{{ $project->status->label() }}</span>
    <span class="tag format ltr">{{ $project->aspect_ratio->value }} · {{ $project->resolution->value }}</span>
</div>
