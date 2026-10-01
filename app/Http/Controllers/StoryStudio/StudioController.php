<?php

namespace App\Http\Controllers\StoryStudio;

use App\Http\Controllers\Controller;
use App\Models\StoryStudio\StoryProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

abstract class StudioController extends Controller
{
    protected function authorizeProject(Request $request, StoryProject $project, string $ability = 'update'): void
    {
        Gate::forUser($request->user())->authorize($ability, $project);
    }

    /**
     * Child records are addressed by id; make sure they belong to the project in the URL.
     */
    protected function ensureBelongs(StoryProject $project, Model $model, ?string $projectId = null): void
    {
        abort_unless(($projectId ?? $model->getAttribute('project_id')) === $project->getKey(), 404);
    }

    protected function nonce(): string
    {
        return (string) Str::uuid();
    }

    protected function done(string $message): RedirectResponse
    {
        return back()->with('status', $message);
    }
}
