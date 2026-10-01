@php($state = $state instanceof \App\Enums\StoryStudio\AssetStatus ? $state : \App\Enums\StoryStudio\AssetStatus::tryFrom((string) $state) ?? \App\Enums\StoryStudio\AssetStatus::Pending)
<span @class(['state', 'state-'.$state->value])>{{ $label ?? '' }} {{ $state->label() }}</span>
