<?php

namespace App\Services\StoryStudio\Providers\Fake;

use App\Services\StoryStudio\Contracts\ImageGenerationProviderInterface;
use App\Services\StoryStudio\Data\ImageResult;

/**
 * Draws a deterministic placeholder illustration with GD.
 */
class FakeImageProvider implements ImageGenerationProviderInterface
{
    public function generate(string $prompt, int $width, int $height, array $referenceImagePaths = []): ImageResult
    {
        $w = $width >= $height ? 1536 : 1024;
        $h = $height > $width ? 1536 : 1024;
        if ($width === $height) {
            $w = $h = 1024;
        }

        $seed = crc32($prompt);
        $image = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = [($seed >> 16) & 0xFF, ($seed >> 8) & 0xFF, $seed & 0xFF];
        [$r2, $g2, $b2] = [255 - $g1, 200 - (int) ($r1 / 3), 160 + (int) ($b1 / 4)];

        for ($y = 0; $y < $h; $y += 4) {
            $t = $y / $h;
            $color = imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) min(255, $b1 + ($b2 - $b1) * $t));
            imagefilledrectangle($image, 0, $y, $w, $y + 3, $color);
        }

        mt_srand($seed);
        for ($i = 0; $i < 9; $i++) {
            $color = imagecolorallocatealpha($image, mt_rand(150, 255), mt_rand(150, 255), mt_rand(150, 255), 70);
            $size = mt_rand(80, 360);
            imagefilledellipse($image, mt_rand(0, $w), mt_rand((int) ($h * 0.3), $h), $size, $size, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 30, 30, 'STORY STUDIO PREVIEW', $white);
        imagestring($image, 3, 30, 56, substr(preg_replace('/[^\x20-\x7E]/', '', $prompt), 0, 110), $white);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return new ImageResult($bytes, 'image/png', $w, $h, 'fake-image', 'fake-'.dechex($seed));
    }

    public function supportsReferenceImages(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-image';
    }
}
