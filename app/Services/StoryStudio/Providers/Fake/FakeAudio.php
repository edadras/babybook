<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Support\Ffmpeg;

/**
 * Synthesizes placeholder MP3s with FFmpeg so the audio/video pipeline is real.
 */
class FakeAudio
{
    public static function render(string $source, float $seconds): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ssfake').'.mp3';
        app(Ffmpeg::class)->run([
            '-f', 'lavfi', '-i', $source.':d='.number_format($seconds, 2, '.', ''),
            '-ac', '2', '-ar', '44100', '-c:a', 'libmp3lame', '-b:a', '96k', $path,
        ], 60);
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}
