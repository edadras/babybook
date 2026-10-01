{{-- Story project wizard. Used by both create and edit. Without JS all steps render stacked. --}}
@php
    $value = fn (string $field) => old($field, $project->{$field} instanceof \BackedEnum ? $project->{$field}->value : $project->{$field});
    $maxChars = config('story-studio.limits.story_max_chars');
    $stepDefs = [
        ['title' => 'اطلاعات داستان', 'hint' => 'عنوان، متن و مخاطب'],
        ['title' => 'سبک تصویری', 'hint' => 'هویت بصری کتاب'],
        ['title' => 'روایت و خروجی', 'hint' => 'صدا، قاب و کیفیت'],
        ['title' => 'مرور و ساخت', 'hint' => 'بررسی نهایی'],
    ];
@endphp

<div class="wizard">
    <ol class="steps" aria-label="مراحل">
        @foreach ($stepDefs as $i => $step)
            <li data-step-marker>
                <button type="button">
                    <span class="num">{{ \App\Support\StoryStudio\Fa::digits($i + 1) }}</span>
                    <span>{{ $step['title'] }}<small>{{ $step['hint'] }}</small></span>
                </button>
            </li>
        @endforeach
    </ol>

    <form method="post" action="{{ $action }}" class="panel" data-wizard novalidate>
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        {{-- Step 1 --}}
        <section class="wizard-step">
            <header class="step-head">
                <h2>اطلاعات داستان</h2>
                <p>متن اصلی نویسنده دست‌نخورده نگه داشته می‌شود؛ کارگردان هوشمند فقط آن را تحلیل و کارگردانی می‌کند.</p>
            </header>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="title">عنوان داستان</label>
                    <input class="input @error('title') is-invalid @enderror" id="title" name="title" value="{{ $value('title') }}" maxlength="190" required placeholder="مثلاً: خرگوش کوچولو و جنگل مه‌آلود">
                    @error('title')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field span-2">
                    <label for="original_text">متن داستان <span class="counter" data-counter-for="original_text"></span></label>
                    <textarea class="textarea story @error('original_text') is-invalid @enderror" id="original_text" name="original_text" maxlength="{{ $maxChars }}" minlength="20" required placeholder="متن کامل داستان را اینجا قرار دهید…">{{ $value('original_text') }}</textarea>
                    @error('original_text')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="language">زبان</label>
                    <select class="select" id="language" name="language" required>
                        <option value="fa" @selected($value('language') === 'fa')>فارسی</option>
                        <option value="en" @selected($value('language') === 'en')>انگلیسی</option>
                    </select>
                    @error('language')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="category">دسته‌بندی</label>
                    <select class="select" id="category" name="category" required>
                        @foreach ($categories as $option)
                            <option value="{{ $option->value }}" @selected($value('category') === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field span-2">
                    <span class="label">گروه سنی مخاطب</span>
                    <div class="choice-grid compact">
                        @foreach ($targetAges as $option)
                            <label class="choice">
                                <input type="radio" name="target_age" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('target_age') === $option->value) required>
                                <span class="choice-body"><span class="choice-title">{{ $option->label() }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('target_age')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Step 2 --}}
        <section class="wizard-step">
            <header class="step-head">
                <h2>سبک تصویری</h2>
                <p>این سبک در «کتاب سبک بصری» قفل می‌شود و در تمام تصاویر ثابت می‌ماند.</p>
            </header>
            <div class="field">
                <div class="choice-grid">
                    @foreach ($visualStyles as $option)
                        <label class="choice">
                            <input type="radio" name="visual_style" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('visual_style') === $option->value) required>
                            <span class="choice-body">
                                <span class="swatch {{ $option->value }}" aria-hidden="true"></span>
                                <span class="choice-title">{{ $option->label() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('visual_style')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field" data-custom-style style="margin-top: 18px">
                <label for="custom_visual_instructions">دستورالعمل تصویری اختصاصی <span data-required-mark hidden>(الزامی برای سبک سفارشی)</span></label>
                <textarea class="textarea @error('custom_visual_instructions') is-invalid @enderror" id="custom_visual_instructions" name="custom_visual_instructions" rows="3" maxlength="2000" placeholder="تصاویر لطیف کتاب کودک، رنگ‌های گرم، بدون ترس، شخصیت‌های بامزه">{{ $value('custom_visual_instructions') }}</textarea>
                <p class="hint">به‌عنوان قید ثابت به همه پرامپت‌های تصویر اضافه می‌شود.</p>
                @error('custom_visual_instructions')<p class="error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Step 3 --}}
        <section class="wizard-step">
            <header class="step-head">
                <h2>روایت و خروجی</h2>
                <p>سبک راوی، قاب ویدیو و نحوه پیشبرد پروژه را انتخاب کنید.</p>
            </header>
            <div class="form-grid">
                <div class="field span-2">
                    <span class="label">سبک روایت</span>
                    <div class="choice-grid">
                        @foreach ($narrationStyles as $option)
                            <label class="choice">
                                <input type="radio" name="narration_style" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('narration_style') === $option->value) required>
                                <span class="choice-body"><span class="choice-title">{{ $option->label() }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('narration_style')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <span class="label">نسبت تصویر</span>
                    <div class="choice-grid compact">
                        @foreach ($aspectRatios as $option)
                            <label class="choice">
                                <input type="radio" name="aspect_ratio" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('aspect_ratio') === $option->value) required>
                                <span class="choice-body" style="text-align:center">
                                    <span class="ratio-shape r-{{ str_replace(':', '-', $option->value) }}" aria-hidden="true"></span>
                                    <span class="choice-title">{{ $option->label() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('aspect_ratio')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <span class="label">کیفیت ویدیو</span>
                    <div class="choice-grid compact">
                        @foreach ($resolutions as $option)
                            <label class="choice">
                                <input type="radio" name="resolution" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('resolution') === $option->value) required>
                                <span class="choice-body" style="text-align:center"><span class="choice-title ltr">{{ $option->label() }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('resolution')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field span-2">
                    <span class="label">حالت ساخت</span>
                    <div class="choice-grid">
                        @foreach ($modes as $option)
                            <label class="choice">
                                <input type="radio" name="mode" value="{{ $option->value }}" data-label="{{ $option->label() }}" @checked($value('mode') === $option->value) required>
                                <span class="choice-body">
                                    <span class="choice-title">{{ $option->label() }}</span>
                                    <span class="choice-desc">{{ $option->value === 'auto' ? 'همه مراحل پشت سر هم اجرا می‌شوند و فقط پیش از هزینه‌کرد تأیید می‌گیریم.' : 'پس از هر مرحله (فیلمنامه، شخصیت‌ها، صداها، استوری‌بورد) برای تأیید شما متوقف می‌شود.' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('mode')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Step 4 --}}
        <section class="wizard-step">
            <header class="step-head">
                <h2>مرور و ساخت</h2>
                <p>با ذخیره، پروژه به حالت پیش‌نویس ساخته می‌شود. هیچ درخواست هزینه‌داری بدون تأیید شما ارسال نمی‌شود.</p>
            </header>
            <dl class="review-list">
                <dt>عنوان</dt><dd data-review="title">{{ $value('title') ?: '—' }}</dd>
                <dt>متن داستان</dt><dd data-review="original_text">—</dd>
                <dt>زبان</dt><dd data-review="language">—</dd>
                <dt>دسته‌بندی</dt><dd data-review="category">—</dd>
                <dt>گروه سنی</dt><dd data-review="target_age">—</dd>
                <dt>سبک تصویری</dt><dd data-review="visual_style">—</dd>
                <dt>سبک روایت</dt><dd data-review="narration_style">—</dd>
                <dt>نسبت تصویر</dt><dd data-review="aspect_ratio">—</dd>
                <dt>کیفیت</dt><dd data-review="resolution">—</dd>
                <dt>حالت ساخت</dt><dd data-review="mode">—</dd>
            </dl>
        </section>

        <footer class="wizard-nav">
            <button type="button" class="btn" data-wizard-prev hidden>→ مرحله قبل</button>
            <span class="grow"></span>
            <a href="{{ $cancelUrl }}" class="btn btn-ghost">انصراف</a>
            <button type="button" class="btn btn-dark" data-wizard-next hidden>مرحله بعد ←</button>
            <button type="submit" class="btn btn-primary" data-wizard-submit>{{ $submitLabel }}</button>
        </footer>
    </form>
</div>
