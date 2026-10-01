@extends('story-studio.layouts.workspace')
@use('App\Support\StoryStudio\Fa')
@section('tab', 'costs')

@section('canvas')
    <div class="canvas-head"><h1>داشبورد هزینه</h1></div>

    <div class="stat-row">
        <div class="stat"><span>هزینه واقعی</span><strong class="ltr">{{ Fa::money($summary['total_actual']) }}</strong></div>
        <div class="stat"><span>تخمین بخش‌های باقی‌مانده</span><strong class="ltr">{{ Fa::money($estimate['total']) }}</strong></div>
        <div class="stat"><span>تصاویر ساخته‌شده</span><strong>{{ Fa::digits($summary['images']) }}</strong></div>
        <div class="stat"><span>نویسه‌های صداسازی</span><strong>{{ Fa::number($summary['tts_characters']) }}</strong></div>
        <div class="stat"><span>مدت صدای ساخته‌شده</span><strong>{{ Fa::duration($summary['audio_duration_ms']) }}</strong></div>
        <div class="stat"><span>فراخوانی API</span><strong>{{ Fa::digits($summary['api_calls']) }}@if ($summary['failed_calls']) <small class="danger-text">({{ Fa::digits($summary['failed_calls']) }} ناموفق)</small>@endif</strong></div>
    </div>

    <section class="panel">
        <h2 class="panel-title">به تفکیک سرویس</h2>
        @php($max = max(0.0001, collect($summary['categories'])->max('actual')))
        <table class="table">
            <thead><tr><th>سرویس</th><th>فراخوانی</th><th>هزینه واقعی</th><th></th></tr></thead>
            @foreach ($summary['categories'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ Fa::digits($row['calls']) }}</td>
                    <td class="ltr">{{ Fa::money($row['actual']) }}</td>
                    <td class="bar-cell"><span class="bar" style="width: {{ round($row['actual'] / $max * 100) }}%"></span></td>
                </tr>
            @endforeach
        </table>
    </section>

    <section class="panel">
        <h2 class="panel-title">آخرین درخواست‌ها</h2>
        <table class="table compact">
            <thead><tr><th>زمان</th><th>عملیات</th><th>مدل</th><th>وضعیت</th><th>واحد</th><th>هزینه</th></tr></thead>
            @foreach ($jobs as $job)
                <tr>
                    <td>{{ Fa::date($job->created_at, true) }}</td>
                    <td class="ltr">{{ $job->operation }}</td>
                    <td class="ltr">{{ $job->model }}</td>
                    <td>{{ $job->status->label() }}@if ($job->attempts > 1) <span class="subtle">(×{{ Fa::digits($job->attempts) }})</span>@endif</td>
                    <td class="ltr">{{ $job->input_units }} {{ $job->unit_type }}</td>
                    <td class="ltr">{{ Fa::money($job->actual_cost) }}</td>
                </tr>
            @endforeach
        </table>
    </section>
@endsection
