<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Contracts\SpeechProviderInterface;
use App\Services\StoryStudio\Data\AudioResult;

class FakeSpeechProvider implements SpeechProviderInterface
{
    public function synthesize(string $text, string $voiceId, array $voiceSettings = []): AudioResult
    {
        $seconds = max(0.8, min(25, mb_strlen($text) * 0.06));
        $frequency = 180 + (crc32($voiceId) % 220);

        return new AudioResult(
            FakeAudio::render('sine=frequency='.$frequency.':sample_rate=44100', $seconds),
            'fake-tts',
            'audio/mpeg',
            mb_strlen($text),
        );
    }

    public function supportsAudioTags(): bool
    {
        return true;
    }

    public function listVoices(): array
    {
        return [
            ['voice_id' => 'fake_narrator', 'name' => 'Fake Narrator', 'description' => 'warm, calm', 'preview_url' => null],
            ['voice_id' => 'fake_child', 'name' => 'Fake Child', 'description' => 'bright, playful', 'preview_url' => null],
        ];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-tts';
    }
}
