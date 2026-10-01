<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStoryStudioAccess;
use App\Models\StoryStudio\StoryProject;
use App\Models\User;
use App\Policies\StoryStudio\StoryProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI Story Studio module into the host application.
 * Everything the module needs is registered here so it stays self-contained.
 */
class StoryStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('story-studio.php'), 'story-studio');
    }

    public function boot(): void
    {
        Gate::define('access-story-studio', function (?User $user): bool {
            if (config('story-studio.access.allow_guests')) {
                return true;
            }

            if (! $user) {
                return false;
            }

            if ((bool) ($user->is_admin ?? false)) {
                return true;
            }

            return in_array(strtolower($user->email), array_map('strtolower', config('story-studio.access.admin_emails', [])), true);
        });

        Gate::policy(StoryProject::class, StoryProjectPolicy::class);

        Route::middleware([...config('story-studio.middleware', ['web']), EnsureStoryStudioAccess::class])
            ->prefix(config('story-studio.route_prefix', 'studio'))
            ->name('story-studio.')
            ->group(base_path('routes/story-studio.php'));
    }
}
