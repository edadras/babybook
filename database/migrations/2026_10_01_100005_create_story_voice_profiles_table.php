<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_voice_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('character_id')->nullable()->constrained('story_characters')->cascadeOnDelete();
            $table->string('provider', 32)->default('elevenlabs');
            // e.g. the ElevenLabs voice_id. Reused for every line of the character.
            $table->string('provider_voice_id', 128)->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            // library | designed | cloned
            $table->string('source', 16)->default('library');
            // Voice Design generation id, needed to persist a designed preview as a voice.
            $table->string('generated_voice_id', 128)->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 16)->default('candidate');
            $table->boolean('locked')->default(false);
            $table->uuid('preview_audio_id')->nullable();
            $table->timestamps();

            $table->index(['character_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_voice_profiles');
    }
};
