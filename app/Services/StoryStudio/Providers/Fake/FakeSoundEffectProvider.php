<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Contracts\SoundEffectProviderInterface;
use App\Services\StoryStudio\Data\AudioResult;

class FakeSoundEffectProvider implements SoundEffectProviderInterface
{
    public function generate(string $prompt, ?float $durationSeconds = null): AudioResult
    {
        return new AudioResult(
            FakeAudio::render('anoisesrc=color=pink:amplitude=0.05:sample_rate=44100', $durationSeconds ?? 2.0),
            'fake-sfx',
        );
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-sfx';
    }
}
