<?php

namespace Tests\Feature\StoryStudio;

use App\Enums\StoryStudio\ProjectStatus;
use App\Enums\StoryStudio\VisualStyle;
use App\Models\StoryStudio\StoryProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoryProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['story-studio.access.allow_guests' => false, 'story-studio.access.admin_emails' => ['admin@example.com']]);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'خرگوش کوچولو و جنگل مه‌آلود',
            'original_text' => "روزی روزگاری خرگوش کوچولویی بود که کوله‌پشتی قرمزش را برداشت.\nخرگوش صدا زد: «مامان! کجایی؟»",
            'language' => 'fa',
            'target_age' => '3-5',
            'category' => 'bedtime',
            'visual_style' => 'watercolor',
            'custom_visual_instructions' => 'رنگ‌های گرم، بدون ترس',
            'narration_style' => 'bedtime',
            'aspect_ratio' => '9:16',
            'resolution' => '720p',
            'mode' => 'manual',
        ], $overrides);
    }

    public function test_dashboard_lists_project_cards(): void
    {
        StoryProject::factory()->create(['title' => 'قصه ماه نقره‌ای']);
        StoryProject::factory()->status(ProjectStatus::Completed, 100)->create(['title' => 'قصه کامل‌شده']);

        $this->get(route('story-studio.index'))
            ->assertOk()
            ->assertSee('قصه ماه نقره‌ای')
            ->assertSee('قصه کامل‌شده')
            ->assertSee('تکمیل شده')
            ->assertSee('ادامه پروژه');
    }

    public function test_dashboard_filters_by_status_and_search(): void
    {
        StoryProject::factory()->create(['title' => 'پیش‌نویس الف']);
        StoryProject::factory()->status(ProjectStatus::Failed)->create(['title' => 'ناموفق ب']);

        $this->get(route('story-studio.index', ['status' => 'failed']))
            ->assertSee('ناموفق ب')
            ->assertDontSee('پیش‌نویس الف');

        $this->get(route('story-studio.index', ['q' => 'الف']))
            ->assertSee('پیش‌نویس الف')
            ->assertDontSee('ناموفق ب');
    }

    public function test_empty_dashboard_invites_a_new_story(): void
    {
        $this->get(route('story-studio.index'))->assertOk()->assertSee('اولین داستانتان را بسازید');
    }

    public function test_wizard_renders_all_options(): void
    {
        $this->get(route('story-studio.projects.create'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تصویرسازی کتاب کودک')
            ->assertSee('۹ تا ۱۲ سال')
            ->assertSee('عمودی ۹:۱۶')
            ->assertSee('1080p');
    }

    public function test_project_is_created_as_draft_with_default_settings(): void
    {
        $response = $this->post(route('story-studio.projects.store'), $this->payload());

        $project = StoryProject::sole();
        $response->assertRedirect(route('story-studio.projects.show', $project));

        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertSame(VisualStyle::Watercolor, $project->visual_style);
        $this->assertSame($this->admin->id, $project->user_id);
        $this->assertSame([720, 1280], $project->frameSize());
        $this->assertTrue(Str::isUuid($project->id));
        $this->assertNotNull($project->settings);
        $this->assertSame('crossfade', $project->settings->default_transition->value);
    }

    public function test_original_text_is_stored_verbatim(): void
    {
        $text = "  خط اول با فاصله.\r\n\r\nخرگوش گفت: «سلام!»  ";

        $this->post(route('story-studio.projects.store'), $this->payload(['original_text' => $text]));

        // Laravel's TrimStrings middleware trims the ends; the body is never rewritten.
        $this->assertSame(trim($text), StoryProject::sole()->original_text);
    }

    public function test_validation_rejects_invalid_input(): void
    {
        $this->from(route('story-studio.projects.create'))
            ->post(route('story-studio.projects.store'), $this->payload([
                'title' => '',
                'original_text' => 'کوتاه',
                'aspect_ratio' => '4:3',
                'target_age' => '13-18',
            ]))
            ->assertRedirect(route('story-studio.projects.create'))
            ->assertSessionHasErrors(['title', 'original_text', 'aspect_ratio', 'target_age']);

        $this->assertDatabaseCount('story_projects', 0);
    }

    public function test_custom_style_requires_instructions(): void
    {
        $this->post(route('story-studio.projects.store'), $this->payload([
            'visual_style' => 'custom',
            'custom_visual_instructions' => '',
        ]))->assertSessionHasErrors('custom_visual_instructions');
    }

    public function test_studio_workspace_shows_project(): void
    {
        $project = StoryProject::factory()->create();

        $this->get(route('story-studio.projects.show', $project))
            ->assertOk()
            ->assertSee($project->title)
            ->assertSee('متن اصلی نویسنده')
            ->assertSee('استوری‌بورد')
            ->assertSee('NARRATOR');
    }

    public function test_project_can_be_updated(): void
    {
        $project = StoryProject::factory()->create();

        $this->get(route('story-studio.projects.edit', $project))->assertOk()->assertSee($project->title);

        $this->put(route('story-studio.projects.update', $project), $this->payload(['title' => 'عنوان تازه', 'mode' => 'auto']))
            ->assertRedirect(route('story-studio.projects.show', $project))
            ->assertSessionHas('status');

        $project->refresh();
        $this->assertSame('عنوان تازه', $project->title);
        $this->assertSame('auto', $project->mode->value);
    }

    public function test_project_cannot_be_edited_while_rendering(): void
    {
        $project = StoryProject::factory()->status(ProjectStatus::Rendering)->create();

        $this->put(route('story-studio.projects.update', $project), $this->payload())->assertStatus(409);
    }

    public function test_project_is_soft_deleted(): void
    {
        $project = StoryProject::factory()->create();

        $this->delete(route('story-studio.projects.destroy', $project))->assertRedirect(route('story-studio.index'));

        $this->assertSoftDeleted($project);
        $this->get(route('story-studio.projects.show', $project))->assertNotFound();
    }

    public function test_progress_endpoint_returns_stage_summary(): void
    {
        $project = StoryProject::factory()->create();
        $project->pipelineRuns()->create([
            'mode' => 'manual',
            'status' => 'running',
            'current_stage' => 'images',
            'stage_states' => [
                'analyze' => ['status' => 'done'],
                'images' => ['status' => 'running', 'done' => 9, 'total' => 12],
            ],
        ]);

        $this->getJson(route('story-studio.projects.progress', $project))
            ->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('current_stage', 'images')
            ->assertJsonPath('stages.0.state', 'done')
            ->assertJsonPath('stages.5.done', 9)
            ->assertJsonPath('stages.5.total', 12)
            ->assertJsonCount(11, 'stages');
    }

    public function test_projects_can_be_scoped_to_their_owner(): void
    {
        config(['story-studio.access.scope_to_owner' => true]);
        $other = StoryProject::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'پروژه دیگران']);
        StoryProject::factory()->create(['user_id' => $this->admin->id, 'title' => 'پروژه من']);

        $this->get(route('story-studio.index'))->assertSee('پروژه من')->assertDontSee('پروژه دیگران');
        $this->get(route('story-studio.projects.show', $other))->assertForbidden();
        $this->put(route('story-studio.projects.update', $other), $this->payload())->assertForbidden();
    }
}
