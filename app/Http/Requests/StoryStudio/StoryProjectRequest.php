<?php

namespace App\Http\Requests\StoryStudio;

use App\Enums\StoryStudio\AspectRatio;
use App\Enums\StoryStudio\GenerationMode;
use App\Enums\StoryStudio\NarrationStyle;
use App\Enums\StoryStudio\Resolution;
use App\Enums\StoryStudio\StoryCategory;
use App\Enums\StoryStudio\TargetAge;
use App\Enums\StoryStudio\VisualStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and editing a story project (wizard step data).
 */
class StoryProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project ? Gate::forUser($this->user())->allows('update', $project) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'original_text' => ['required', 'string', 'min:20', 'max:'.config('story-studio.limits.story_max_chars')],
            'language' => ['required', Rule::in(['fa', 'en'])],
            'target_age' => ['required', Rule::enum(TargetAge::class)],
            'category' => ['required', Rule::enum(StoryCategory::class)],
            'visual_style' => ['required', Rule::enum(VisualStyle::class)],
            'custom_visual_instructions' => [
                Rule::requiredIf(fn () => $this->input('visual_style') === VisualStyle::Custom->value),
                'nullable', 'string', 'max:2000',
            ],
            'narration_style' => ['required', Rule::enum(NarrationStyle::class)],
            'aspect_ratio' => ['required', Rule::enum(AspectRatio::class)],
            'resolution' => ['required', Rule::enum(Resolution::class)],
            'mode' => ['required', Rule::enum(GenerationMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان',
            'original_text' => 'متن داستان',
            'language' => 'زبان',
            'target_age' => 'گروه سنی',
            'category' => 'دسته‌بندی',
            'visual_style' => 'سبک تصویری',
            'custom_visual_instructions' => 'دستورالعمل تصویری',
            'narration_style' => 'سبک روایت',
            'aspect_ratio' => 'نسبت تصویر',
            'resolution' => 'کیفیت',
            'mode' => 'حالت ساخت',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'فیلد :attribute الزامی است.',
            'required_if' => 'برای سبک سفارشی، :attribute را بنویسید.',
            'min' => ':attribute خیلی کوتاه است.',
            'max' => ':attribute بیش از حد طولانی است.',
            'in' => 'مقدار :attribute معتبر نیست.',
            'enum' => 'مقدار :attribute معتبر نیست.',
        ];
    }
}
