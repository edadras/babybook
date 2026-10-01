<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_project_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->unique()->constrained('story_projects')->cascadeOnDelete();
            $table->string('default_motion', 16)->default('auto');
            $table->string('default_transition', 16)->default('crossfade');
            $table->unsignedSmallInteger('transition_duration_ms')->default(600);
            $table->decimal('music_volume', 4, 2)->default(0.35);
            // How far music drops (dB) while a voice is speaking.
            $table->unsignedTinyInteger('ducking_db')->default(12);
            $table->string('subtitle_mode', 16)->default('fa');
            $table->boolean('burn_subtitles')->default(false);
            $table->unsignedTinyInteger('subtitle_font_size')->default(42);
            $table->boolean('show_title_on_cover')->default(true);
            // Per-stage provider overrides, e.g. {"image": "openai"}.
            $table->json('provider_overrides')->nullable();
            $table->decimal('budget_limit', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_project_settings');
    }
};
