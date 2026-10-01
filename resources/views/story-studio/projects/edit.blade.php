@extends('story-studio.layouts.studio')

@section('title', 'ویرایش '.$project->title)
@section('crumb', $project->title)

@section('body')
<main class="page">
    @include('story-studio.partials.flash')
    <div class="page-head">
        <div>
            <h1>ویرایش پروژه</h1>
            <p class="lead">تغییر متن داستان فقط بخش‌های وابسته را برای بازسازی علامت می‌زند؛ کارهای تأییدشده حفظ می‌شوند.</p>
        </div>
    </div>

    @include('story-studio.projects._form', [
        'action' => route('story-studio.projects.update', $project),
        'method' => 'PUT',
        'submitLabel' => 'ذخیره تغییرات',
        'cancelUrl' => route('story-studio.projects.show', $project),
    ])
</main>
@endsection
