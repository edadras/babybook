@extends('story-studio.layouts.studio')

@section('title', 'داستان جدید')
@section('crumb', 'داستان جدید')

@section('body')
<main class="page">
    @include('story-studio.partials.flash')
    <div class="page-head">
        <div>
            <h1>داستان جدید</h1>
            <p class="lead">چهار گام کوتاه تا آماده شدن پروژه برای کارگردانی.</p>
        </div>
    </div>

    @include('story-studio.projects._form', [
        'action' => route('story-studio.projects.store'),
        'method' => 'POST',
        'submitLabel' => 'ساخت پروژه ✦',
        'cancelUrl' => route('story-studio.index'),
    ])
</main>
@endsection
