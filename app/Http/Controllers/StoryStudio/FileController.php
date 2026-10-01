<?php

namespace App\Http\Controllers\StoryStudio;

use App\Models\StoryStudio\StoryAsset;
use App\Models\StoryStudio\StoryAudioAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams private generated files after authorization (supports HTTP Range for seeking).
 */
class FileController extends StudioController
{
    public function show(Request $request, string $kind, string $id): Response
    {
        $asset = match ($kind) {
            'asset' => StoryAsset::with('project')->findOrFail($id),
            'audio' => StoryAudioAsset::with('project')->findOrFail($id),
            default => abort(404),
        };
        Gate::forUser($request->user())->authorize('view', $asset->project);
        abort_unless($asset->file_path, 404);

        $diskName = $asset->disk ?: config('story-studio.storage.disk');
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($asset->file_path), 404);

        $downloadName = $request->boolean('download')
            ? str($asset->project->title)->slug('_', null)->append('_', basename($asset->file_path))->toString()
            : null;

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            $response = response()->file($disk->path($asset->file_path), ['Cache-Control' => 'private, max-age=86400']);
            if ($downloadName) {
                $response->setContentDisposition('attachment', $downloadName, 'download.'.pathinfo($asset->file_path, PATHINFO_EXTENSION));
            }

            return $response;
        }

        return redirect()->away($disk->temporaryUrl($asset->file_path, now()->addMinutes(30), $downloadName ? ['ResponseContentDisposition' => 'attachment; filename="'.$downloadName.'"'] : []));
    }
}
