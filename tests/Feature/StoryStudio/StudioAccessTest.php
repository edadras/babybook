<?php

namespace Tests\Feature\StoryStudio;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_rejected_by_default(): void
    {
        config(['story-studio.access.allow_guests' => false]);

        $this->get(route('story-studio.index'))->assertUnauthorized();
        $this->getJson(route('story-studio.index'))->assertUnauthorized();
    }

    public function test_non_admin_users_are_forbidden(): void
    {
        config(['story-studio.access.allow_guests' => false, 'story-studio.access.admin_emails' => ['admin@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get(route('story-studio.index'))
            ->assertForbidden();
    }

    public function test_admin_emails_are_allowed_case_insensitively(): void
    {
        config(['story-studio.access.allow_guests' => false, 'story-studio.access.admin_emails' => ['Admin@Example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']))
            ->get(route('story-studio.index'))
            ->assertOk()
            ->assertSee('استودیو داستان');
    }

    public function test_guest_access_can_be_enabled_for_local_development(): void
    {
        config(['story-studio.access.allow_guests' => true]);

        $this->get(route('story-studio.index'))->assertOk();
    }

    public function test_api_keys_never_reach_the_browser(): void
    {
        config([
            'story-studio.access.allow_guests' => true,
            'services.openai.key' => 'sk-test-secret-openai',
            'services.elevenlabs.key' => 'el-test-secret',
        ]);

        $this->get(route('story-studio.projects.create'))
            ->assertOk()
            ->assertDontSee('sk-test-secret-openai')
            ->assertDontSee('el-test-secret');
    }
}
