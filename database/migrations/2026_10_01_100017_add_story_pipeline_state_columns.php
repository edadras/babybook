<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-item work state (for idempotent orchestration and stale tracking)
     * and the approval gates of manual studio mode.
     */
    public function up(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            // {"cover": "ready", "final": "stale"}
            $table->json('work_state')->nullable()->after('progress');
            $table->timestamp('script_approved_at')->nullable();
            $table->timestamp('characters_approved_at')->nullable();
            $table->timestamp('voices_approved_at')->nullable();
            $table->timestamp('storyboard_approved_at')->nullable();
            $table->timestamp('review_approved_at')->nullable();
            $table->timestamp('cost_confirmed_at')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::table('story_scenes', function (Blueprint $table) {
            // {"image": "ready", "narration": "stale", "mix": "queued", "video": "pending"}
            $table->json('work_state')->nullable()->after('status');
            $table->text('narration_en')->nullable()->after('narration_tts');
        });

        Schema::table('story_dialogues', function (Blueprint $table) {
            $table->text('text_en')->nullable()->after('text_tts');
        });

        Schema::table('story_characters', function (Blueprint $table) {
            $table->text('reference_prompt')->nullable()->after('special_features');
            $table->string('voice_status', 16)->default('pending')->after('voice_profile_id');
        });

        Schema::table('story_project_settings', function (Blueprint $table) {
            $table->uuid('default_music_audio_id')->nullable()->after('music_volume');
        });
    }

    public function down(): void
    {
        Schema::table('story_project_settings', fn (Blueprint $table) => $table->dropColumn('default_music_audio_id'));
        Schema::table('story_characters', fn (Blueprint $table) => $table->dropColumn(['reference_prompt', 'voice_status']));
        Schema::table('story_dialogues', fn (Blueprint $table) => $table->dropColumn('text_en'));
        Schema::table('story_scenes', fn (Blueprint $table) => $table->dropColumn(['work_state', 'narration_en']));
        Schema::table('story_projects', fn (Blueprint $table) => $table->dropColumn([
            'work_state', 'script_approved_at', 'characters_approved_at', 'voices_approved_at',
            'storyboard_approved_at', 'review_approved_at', 'cost_confirmed_at', 'estimated_cost', 'last_error',
        ]));
    }
};
