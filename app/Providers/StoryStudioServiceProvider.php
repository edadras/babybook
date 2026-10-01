<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStoryStudioAccess;
use App\Models\StoryStudio\StoryProject;
use App\Models\User;
use App\Policies\StoryStudio\StoryProjectPolicy;
use App\Services\StoryStudio\Contracts\ImageGenerationProviderInterface;
use App\Services\StoryStudio\Contracts\SoundEffectProviderInterface;
use App\Services\StoryStudio\Contracts\SpeechProviderInterface;
use App\Services\StoryStudio\Contracts\StoryDirectorProviderInterface;
use App\Services\StoryStudio\Contracts\VoiceDesignProviderInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Wires the AI Story Studio module into the host application.
 * Everything the module needs is registered here so it stays self-contained.
 */
class StoryStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('story-studio.php'), 'story-studio');

        $contracts = [
            'director' => StoryDirectorProviderInterface::class,
            'image' => ImageGenerationProviderInterface::class,
            'speech' => SpeechProviderInterface::class,
            'voice_design' => VoiceDesignProviderInterface::class,
            'sound_effects' => SoundEffectProviderInterface::class,
        ];

        foreach ($contracts as $capability => $contract) {
            $this->app->bind($contract, function ($app) use ($capability) {
                $name = config("story-studio.providers.{$capability}");
                $class = config("story-studio.provider_classes.{$capability}.{$name}");

                if (! $class) {
                    throw new InvalidArgumentException("Unknown Story Studio {$capability} provider [{$name}].");
                }

                return $app->make($class);
            });
        }
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
