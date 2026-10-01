<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Contracts\VoiceDesignProviderInterface;
use App\Services\StoryStudio\Data\VoicePreview;

class FakeVoiceDesignProvider implements VoiceDesignProviderInterface
{
    public function design(string $description, string $sampleText): array
    {
        $previews = [];
        foreach ([220, 330, 440] as $index => $frequency) {
            $previews[] = new VoicePreview(
                'fake_gen_'.substr(md5($description.$index), 0, 10),
                FakeAudio::render('sine=frequency='.$frequency.':sample_rate=44100', 1.2),
                'audio/mpeg',
                1200,
            );
        }

        return $previews;
    }

    public function createVoice(string $generatedVoiceId, string $name, string $description): string
    {
        return 'fake_voice_'.substr(md5($generatedVoiceId), 0, 10);
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-voice-design';
    }
}
