<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_scenes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('story_locations')->nullOnDelete();
            $table->unsignedSmallInteger('scene_number');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title')->nullable();
            $table->string('time_of_day', 32)->nullable();

            $table->unsignedInteger('duration_estimate_ms')->nullable();
            // Editor override; falls back to the estimate or audio length.
            $table->unsignedInteger('duration_ms')->nullable();

            // Original narration from the story and the pronunciation-normalised TTS text.
            $table->text('narration_original')->nullable();
            $table->text('narration_tts')->nullable();

            // Director output: description, shot_type, camera_angle, lighting, character_actions.
            $table->json('visual')->nullable();
            $table->text('director_prompt')->nullable();
            $table->text('final_image_prompt')->nullable();
            $table->boolean('prompt_edited')->default(false);
            // ambience, music_mood (individual effects live in story_sound_effects).
            $table->json('audio_plan')->nullable();

            $table->string('motion', 16)->default('auto');
            $table->string('transition', 16)->default('crossfade');
            $table->unsignedSmallInteger('transition_duration_ms')->nullable();

            $table->string('music_mood', 32)->nullable();
            $table->uuid('music_audio_id')->nullable();
            $table->decimal('music_volume', 4, 2)->nullable();
            $table->unsignedInteger('music_fade_in_ms')->default(0);
            $table->unsignedInteger('music_fade_out_ms')->default(0);

            $table->string('status', 16)->default('pending');
            // Which derived outputs are stale, e.g. {"image": true, "mix": true}.
            $table->json('stale_flags')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('story_scene_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('scene_id')->constrained('story_scenes')->cascadeOnDelete();
            $table->foreignUuid('character_id')->constrained('story_characters')->cascadeOnDelete();
            $table->text('action')->nullable();
            $table->string('position', 64)->nullable();
            $table->timestamps();

            $table->unique(['scene_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_scene_characters');
        Schema::dropIfExists('story_scenes');
    }
};
