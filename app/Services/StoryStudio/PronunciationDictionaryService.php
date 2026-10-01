<?php

namespace App\Services\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Models\StoryStudio\StoryPronunciation;

/**
 * Persian pronunciation normalisation: turns original text into the text
 * sent to TTS. The original text is never modified; callers store the result
 * separately (narration_tts / text_tts).
 */
class PronunciationDictionaryService
{
    /** @var array<string, list<StoryPronunciation>> */
    private array $cache = [];

    public function normalize(string $text, ?StoryProject $project): string
    {
        $text = $this->baseNormalize($text);

        foreach ($this->rules($project) as $rule) {
            $word = $this->baseNormalize($rule->word);
            if ($word === '') {
                continue;
            }

            if ($rule->whole_word) {
                // Persian-aware word boundary: not adjacent to letters, marks or ZWNJ.
                $pattern = '/(?<![\p{L}\p{M}\x{200C}])'.preg_quote($word, '/').'(?![\p{L}\p{M}\x{200C}])/u';
                $text = (string) preg_replace($pattern, $rule->pronunciation, $text);
            } else {
                $text = str_replace($word, $rule->pronunciation, $text);
            }
        }

        return $text;
    }

    /**
     * Unify Arabic/Persian letter variants and digits so dictionary entries match.
     */
    public function baseNormalize(string $text): string
    {
        $text = strtr($text, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'هٔ', 'ة' => 'ه',
            '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴', '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
        ]);

        return trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    }

    /**
     * Longest words first, project entries override global ones.
     *
     * @return list<StoryPronunciation>
     */
    public function rules(?StoryProject $project): array
    {
        $cacheKey = $project?->getKey() ?? 'global';

        return $this->cache[$cacheKey] ??= StoryPronunciation::query()
            ->applicableTo($project)
            ->get()
            ->sortByDesc(fn (StoryPronunciation $rule) => [$rule->project_id !== null, mb_strlen($rule->word)])
            ->unique('word')
            ->sortByDesc(fn (StoryPronunciation $rule) => mb_strlen($rule->word))
            ->values()
            ->all();
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
