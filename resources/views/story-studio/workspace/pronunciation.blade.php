@extends('story-studio.layouts.workspace')
@section('tab', 'pronunciation')

@section('canvas')
    <div class="canvas-head">
        <h1>دیکشنری تلفظ فارسی</h1>
        <span class="muted">پیش از ساخت صدا، متن با این قواعد اصلاح می‌شود. متن اصلی داستان هرگز تغییر نمی‌کند.</span>
    </div>

    <section class="panel">
        <form method="post" action="{{ route('story-studio.projects.pronunciation.store', $project) }}" class="inline-form">
            @csrf
            <input class="input" name="word" placeholder="واژه (مثلاً بندر لنگه)" required value="{{ old('word') }}">
            <input class="input" name="pronunciation" placeholder="تلفظ صحیح (مثلاً بَندَرِ لِنگه)" required value="{{ old('pronunciation') }}">
            <select class="select" name="type">@foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>
            <select class="select" name="scope"><option value="project">فقط این پروژه</option><option value="global">همه پروژه‌ها</option></select>
            <label class="check"><input type="checkbox" name="partial" value="1"> بخشی از کلمه</label>
            <button class="btn btn-sm btn-dark">＋ افزودن</button>
        </form>
        @error('word')<p class="error">{{ $message }}</p>@enderror

        <table class="table">
            <thead><tr><th>واژه</th><th>تلفظ صحیح</th><th>نوع</th><th>دامنه</th><th></th></tr></thead>
            @forelse ($entries as $entry)
                <tr>
                    <td><strong>{{ $entry->word }}</strong></td>
                    <td>{{ $entry->pronunciation }}</td>
                    <td>{{ $entry->type->label() }}</td>
                    <td>{{ $entry->project_id ? 'این پروژه' : 'سراسری' }}</td>
                    <td><form method="post" action="{{ route('story-studio.projects.pronunciation.destroy', [$project, $entry]) }}" data-confirm="حذف شود؟">@csrf @method('DELETE')<button class="link-btn danger">حذف</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="subtle">هنوز قاعده‌ای ثبت نشده است. نام شخصیت‌ها، نام‌های ایرانی و مکان‌های خاص را اضافه کنید.</td></tr>
            @endforelse
        </table>
    </section>

    <section class="panel">
        <h2 class="panel-title">آزمایش</h2>
        <form method="get" class="stack-form">
            <textarea class="textarea" rows="3" name="sample">{{ $sample }}</textarea>
            <button class="btn btn-sm">نمایش متن ارسالی به TTS</button>
        </form>
        <div class="compare">
            <div><span class="label">original_text</span><p>{{ $sample }}</p></div>
            <div><span class="label">tts_text</span><p>{{ $normalized }}</p></div>
        </div>
    </section>
@endsection
