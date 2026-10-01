<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\SubtitleMode;

/**
 * Builds subtitle cues from the final script timing and writes SRT, VTT and
 * ASS (for burn-in). Persian lines are wrapped in RTL marks so punctuation
 * renders correctly in players.
 */
class SubtitleService
{
    private const MAX_CHARS = 70;

    private const RLM = "\u{200F}";

    /**
     * @param  list<array{start_ms: int, plan: array<string, mixed>}>  $scenes  global start + scene plan
     * @return list<array{start: int, end: int, fa: string, en: ?string}>
     */
    public function cues(array $scenes): array
    {
        $cues = [];

        foreach ($scenes as $scene) {
            foreach ($scene['plan']['voice'] as $line) {
                $start = $scene['start_ms'] + $line['start_ms'];
                $chunks = $this->chunk($line['text']);
                $chunksEn = $line['text_en'] ? $this->chunk($line['text_en'], count($chunks)) : [];
                $total = max(1, array_sum(array_map('mb_strlen', $chunks)));
                $cursor = $start;

                foreach ($chunks as $index => $chunk) {
                    $length = (int) round($line['duration_ms'] * mb_strlen($chunk) / $total);
                    $cues[] = [
                        'start' => $cursor,
                        'end' => $cursor + max(700, $length),
                        'fa' => $chunk,
                        'en' => $chunksEn[$index] ?? null,
                    ];
                    $cursor += $length;
                }
            }
        }

        // Never overlap consecutive cues.
        for ($i = 0; $i < count($cues) - 1; $i++) {
            $cues[$i]['end'] = min($cues[$i]['end'], $cues[$i + 1]['start'] - 20);
        }

        return $cues;
    }

    /**
     * @param  list<array{start: int, end: int, fa: string, en: ?string}>  $cues
     */
    public function srt(array $cues, SubtitleMode $mode): string
    {
        $out = [];
        foreach ($cues as $index => $cue) {
            $out[] = ($index + 1)."\n".$this->time($cue['start'], ',').' --> '.$this->time($cue['end'], ',')."\n".implode("\n", $this->lines($cue, $mode))."\n";
        }

        return implode("\n", $out);
    }

    /**
     * @param  list<array{start: int, end: int, fa: string, en: ?string}>  $cues
     */
    public function vtt(array $cues, SubtitleMode $mode): string
    {
        $out = ["WEBVTT\n"];
        foreach ($cues as $cue) {
            $out[] = $this->time($cue['start'], '.').' --> '.$this->time($cue['end'], '.')." line:88% align:center\n".implode("\n", $this->lines($cue, $mode))."\n";
        }

        return implode("\n", $out);
    }

    /**
     * ASS subtitles for burn-in: large, readable, bottom safe area.
     *
     * @param  list<array{start: int, end: int, fa: string, en: ?string}>  $cues
     */
    public function ass(array $cues, SubtitleMode $mode, int $width, int $height, int $fontSize, string $font): string
    {
        // Sized for phones: the setting is in 1080p points, scaled up 30% for readability.
        $size = (int) round($fontSize * 1.3 * $height / 1080);
        $margin = (int) round($height * 0.07);
        $header = "[Script Info]\nScriptType: v4.00+\nPlayResX: {$width}\nPlayResY: {$height}\nWrapStyle: 0\n\n"
            ."[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n"
            ."Style: Default,{$font},{$size},&H00FFFFFF,&H00FFFFFF,&H00201A2A,&H96000000,-1,0,0,0,100,100,0,0,1,".max(2, (int) ($size / 14)).',1,2,'.(int) ($width * 0.08).','.(int) ($width * 0.08).",{$margin},1\n\n"
            ."[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        $events = [];
        foreach ($cues as $cue) {
            $lines = $this->lines($cue, $mode, false);
            $text = implode('\\N', array_map(fn ($line, $i) => $i > 0 && $mode === SubtitleMode::Bilingual ? '{\\fs'.(int) ($size * 0.75).'}'.$line : $line, $lines, array_keys($lines)));
            $events[] = 'Dialogue: 0,'.$this->assTime($cue['start']).','.$this->assTime($cue['end']).',Default,,0,0,0,,'.str_replace(["\n", '{', '}'], [' ', '(', ')'], $text);
        }

        return $header.implode("\n", $events)."\n";
    }

    /**
     * @param  array{fa: string, en: ?string}  $cue
     * @return list<string>
     */
    private function lines(array $cue, SubtitleMode $mode, bool $marks = true): array
    {
        $fa = $marks ? self::RLM.$cue['fa'].self::RLM : $cue['fa'];

        return match ($mode) {
            SubtitleMode::English => [$cue['en'] ?? $fa],
            SubtitleMode::Bilingual => array_values(array_filter([$fa, $cue['en']])),
            default => [$fa],
        };
    }

    /**
     * Split text into readable chunks on sentence/clause boundaries.
     *
     * @return list<string>
     */
    public function chunk(string $text, ?int $targetCount = null): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }

        $pieces = preg_split('/(?<=[.!؟?…])\s+|(?<=[،,؛;])\s+/u', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            foreach ($this->hardWrap($piece) as $part) {
                if ($current !== '' && mb_strlen($current.' '.$part) > self::MAX_CHARS) {
                    $chunks[] = $current;
                    $current = $part;
                } else {
                    $current = trim($current.' '.$part);
                }
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        if ($targetCount !== null && count($chunks) !== $targetCount && $targetCount > 0) {
            // Re-balance translation chunks to the number of Persian chunks.
            $words = preg_split('/\s+/u', $text) ?: [];
            $per = (int) ceil(count($words) / $targetCount);

            return array_values(array_map(fn ($group) => implode(' ', $group), array_chunk($words, max(1, $per))));
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function hardWrap(string $piece): array
    {
        if (mb_strlen($piece) <= self::MAX_CHARS) {
            return [$piece];
        }

        $parts = [];
        $line = '';
        foreach (preg_split('/\s+/u', $piece) ?: [] as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > self::MAX_CHARS) {
                $parts[] = $line;
                $line = $word;
            } else {
                $line = trim($line.' '.$word);
            }
        }
        if ($line !== '') {
            $parts[] = $line;
        }

        return $parts;
    }

    private function time(int $ms, string $separator): string
    {
        $ms = max(0, $ms);

        return sprintf('%02d:%02d:%02d%s%03d', intdiv($ms, 3600000), intdiv($ms, 60000) % 60, intdiv($ms, 1000) % 60, $separator, $ms % 1000);
    }

    private function assTime(int $ms): string
    {
        $ms = max(0, $ms);

        return sprintf('%d:%02d:%02d.%02d', intdiv($ms, 3600000), intdiv($ms, 60000) % 60, intdiv($ms, 1000) % 60, intdiv($ms % 1000, 10));
    }
}
