<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned audio outputs (narration, dialogue lines, sfx, ambience, music, mixes).
     */
    public function up(): void
    {
        Schema::create('story_audio_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('scene_id')->nullable()->constrained('story_scenes')->nullOnDelete();
            $table->foreignUuid('dialogue_id')->nullable()->constrained('story_dialogues')->nullOnDelete();
            $table->foreignUuid('character_id')->nullable()->constrained('story_characters')->nullOnDelete();
            $table->foreignUuid('sound_effect_id')->nullable()->constrained('story_sound_effects')->nullOnDelete();
            $table->foreignUuid('voice_profile_id')->nullable()->constrained('story_voice_profiles')->nullOnDelete();
            $table->foreignUuid('generation_job_id')->nullable()->constrained('story_generation_jobs')->nullOnDelete();
            $table->string('type', 32);
            $table->string('slot', 128);
            $table->unsignedInteger('version');
            $table->boolean('selected')->default(false);
            $table->string('status', 16)->default('pending');
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('voice_id', 128)->nullable();
            // Text exactly as written vs. the text sent to TTS (pronunciation-normalised + tags).
            $table->text('text')->nullable();
            $table->text('tts_text')->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('generation_time_ms')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slot', 'version']);
            $table->index(['slot', 'selected']);
            $table->index(['scene_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_audio_assets');
    }
};
