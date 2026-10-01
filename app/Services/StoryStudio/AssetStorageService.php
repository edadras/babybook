<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\AssetType;
use App\Enums\StoryStudio\AudioAssetType;
use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryAudioAsset;
use App\Models\StoryStudio\StoryProject;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes generated files to the configured disk and records them as new,
 * append-only asset versions. Nothing is ever overwritten.
 */
class AssetStorageService
{
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('story-studio.storage.disk', 'local');
    }

    /**
     * @param  array<string, mixed>  $attributes  extra columns (scene_id, prompt, metadata, ...)
     */
    public function storeVisual(StoryProject $project, AssetType $type, string $slot, string $baseName, string $bytes, string $extension, array $attributes = [], bool $select = true): StoryAsset
    {
        return $this->store(StoryAsset::class, $project, $type->value, $slot, 'images', $baseName, $bytes, $extension, $attributes, $select);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function storeAudio(StoryProject $project, AudioAssetType $type, string $slot, string $baseName, string $bytes, string $extension, array $attributes = [], bool $select = true): StoryAudioAsset
    {
        return $this->store(StoryAudioAsset::class, $project, $type->value, $slot, 'audio', $baseName, $bytes, $extension, $attributes, $select);
    }

    /**
     * Store a file that already exists locally (e.g. FFmpeg output).
     *
     * @param  class-string<StoryAsset|StoryAudioAsset>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    public function storeLocalFile(string $modelClass, StoryProject $project, string $type, string $slot, string $folder, string $baseName, string $localPath, string $extension, array $attributes = [], bool $select = true): StoryAsset|StoryAudioAsset
    {
        $stream = fopen($localPath, 'rb');

        try {
            return $this->store($modelClass, $project, $type, $slot, $folder, $baseName, $stream, $extension, $attributes, $select);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Run $callback with a local filesystem path for the asset (downloads from remote disks).
     *
     * @template T
     *
     * @param  Closure(string): T  $callback
     * @return T
     */
    public function withLocalPath(StoryAsset|StoryAudioAsset $asset, Closure $callback): mixed
    {
        $disk = Storage::disk($asset->disk ?: $this->diskName());

        if (method_exists($disk, 'path') && config('filesystems.disks.'.($asset->disk ?: $this->diskName()).'.driver') === 'local') {
            return $callback($disk->path($asset->file_path));
        }

        $temp = $this->tempPath(pathinfo($asset->file_path, PATHINFO_EXTENSION));
        file_put_contents($temp, $disk->readStream($asset->file_path));

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }

    public function isLocalDisk(StoryAsset|StoryAudioAsset $asset): bool
    {
        return config('filesystems.disks.'.($asset->disk ?: $this->diskName()).'.driver') === 'local';
    }

    /**
     * Local path of the file. For remote disks this downloads a temporary copy
     * which the caller must delete (see isLocalDisk()).
     */
    public function localPath(StoryAsset|StoryAudioAsset $asset): string
    {
        $diskName = $asset->disk ?: $this->diskName();

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return Storage::disk($diskName)->path($asset->file_path);
        }

        $temp = $this->tempPath(pathinfo($asset->file_path, PATHINFO_EXTENSION));
        file_put_contents($temp, Storage::disk($diskName)->get($asset->file_path));

        return $temp;
    }

    public function workDir(string $prefix = 'render'): string
    {
        $dir = storage_path('app/story-studio-tmp/'.$prefix.'-'.Str::uuid());
        @mkdir($dir, 0775, true);

        return $dir;
    }

    public function tempPath(string $extension): string
    {
        $dir = storage_path('app/story-studio-tmp');
        @mkdir($dir, 0775, true);

        return $dir.'/'.Str::uuid().'.'.ltrim($extension, '.');
    }

    public static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir.'/'.$file;
                is_dir($path) ? self::deleteDirectory($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * @param  class-string<StoryAsset|StoryAudioAsset>  $modelClass
     * @param  string|resource  $contents
     * @param  array<string, mixed>  $attributes
     */
    private function store(string $modelClass, StoryProject $project, string $type, string $slot, string $folder, string $baseName, mixed $contents, string $extension, array $attributes, bool $select): StoryAsset|StoryAudioAsset
    {
        for ($attempt = 1; ; $attempt++) {
            $version = $modelClass::nextVersion($project->getKey(), $slot);
            $path = sprintf('%s/%s/%s/%s_v%d.%s', trim(config('story-studio.storage.root'), '/'), $project->getKey(), $folder, Str::slug($baseName, '_') ?: 'asset', $version, $extension);

            try {
                /** @var StoryAsset|StoryAudioAsset $asset */
                $asset = $modelClass::create(array_merge([
                    'project_id' => $project->getKey(),
                    'type' => $type,
                    'slot' => $slot,
                    'version' => $version,
                    'status' => AssetStatus::Generating->value,
                    'disk' => $this->diskName(),
                    'file_path' => $path,
                ], $attributes));
                break;
            } catch (QueryException $exception) {
                // Another worker took this version number; try the next one.
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }

        $this->disk()->put($path, $contents);

        $asset->forceFill([
            'status' => AssetStatus::Ready,
            'file_size' => $this->disk()->size($path),
            'mime_type' => $this->disk()->mimeType($path) ?: null,
        ])->save();

        if ($select) {
            $asset->select();
        }

        return $asset;
    }
}
