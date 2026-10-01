<?php

namespace App\Support\StoryStudio;

use DateTimeInterface;
use IntlDateFormatter;

/**
 * Persian presentation helpers: Persian digits, Jalali dates, durations.
 */
final class Fa
{
    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function digits(int|float|string|null $value): string
    {
        return str_replace(self::LATIN, self::PERSIAN, (string) $value);
    }

    public static function number(int|float|null $value, int $decimals = 0): string
    {
        return self::digits(number_format((float) $value, $decimals, '٫', '٬'));
    }

    /**
     * Jalali date, e.g. "۹ مهر ۱۴۰۵". Falls back to Gregorian if intl lacks the calendar.
     */
    public static function date(?DateTimeInterface $date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                'fa_IR@calendar=persian',
                IntlDateFormatter::LONG,
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                config('app.timezone'),
                IntlDateFormatter::TRADITIONAL,
            );

            $formatted = $formatter->format($date);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return self::digits($date->format($withTime ? 'Y/m/d H:i' : 'Y/m/d'));
    }

    /**
     * Duration as m:ss in Persian digits, e.g. "۳:۰۵".
     */
    public static function duration(?int $milliseconds): string
    {
        if (! $milliseconds) {
            return '—';
        }

        $seconds = intdiv($milliseconds + 500, 1000);

        return self::digits(sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60));
    }

    public static function language(?string $code): string
    {
        return match ($code) {
            'fa' => 'فارسی',
            'en' => 'انگلیسی',
            default => (string) $code,
        };
    }
}
