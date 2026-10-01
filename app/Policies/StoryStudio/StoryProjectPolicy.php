<?php

namespace App\Policies\StoryStudio;

use App\Models\StoryStudio\StoryProject;
use App\Models\User;

class StoryProjectPolicy
{
    public function view(?User $user, StoryProject $project): bool
    {
        return $this->owns($user, $project);
    }

    public function update(?User $user, StoryProject $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(?User $user, StoryProject $project): bool
    {
        return $this->owns($user, $project);
    }

    private function owns(?User $user, StoryProject $project): bool
    {
        if (! config('story-studio.access.scope_to_owner')) {
            return true;
        }

        return $user !== null && (int) $project->user_id === (int) $user->getKey();
    }
}
