@if ($asset)
    <audio class="audio" controls preload="none" src="{{ route('story-studio.files.show', ['audio', $asset->id]) }}"></audio>
@else
    <span class="subtle">{{ $empty ?? 'هنوز ساخته نشده' }}</span>
@endif
