<?php

namespace App\Enums\StoryStudio;

enum AspectRatio: string
{
    use HasOptions;

    case Landscape = '16:9';
    case Portrait = '9:16';
    case Square = '1:1';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'افقی ۱۶:۹',
            self::Portrait => 'عمودی ۹:۱۶',
            self::Square => 'مربع ۱:۱',
        };
    }

    /**
     * Output frame size in pixels for the given resolution.
     *
     * @return array{0: int, 1: int}
     */
    public function dimensions(Resolution $resolution): array
    {
        $short = $resolution->shortEdge();

        return match ($this) {
            self::Landscape => [intdiv($short * 16, 9), $short],
            self::Portrait => [$short, intdiv($short * 16, 9)],
            self::Square => [$short, $short],
        };
    }
}
