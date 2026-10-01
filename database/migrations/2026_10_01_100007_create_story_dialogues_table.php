<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_dialogues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scene_id')->constrained('story_scenes')->cascadeOnDelete();
            $table->foreignUuid('character_id')->nullable()->constrained('story_characters')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('text_original');
            $table->text('text_tts')->nullable();
            // Voice direction from the AI director.
            $table->string('emotion', 64)->nullable();
            $table->string('energy', 32)->nullable();
            $table->string('pace', 32)->nullable();
            $table->string('delivery')->nullable();
            // Provider-specific expressive tags, e.g. ElevenLabs "[worried]".
            $table->json('provider_tags')->nullable();
            $table->unsignedInteger('start_offset_ms')->nullable();
            $table->decimal('volume', 4, 2)->default(1);
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['scene_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_dialogues');
    }
};
