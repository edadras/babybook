<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Continuity snapshot *after* each scene (outfits, props, weather, time, objects).
     */
    public function up(): void
    {
        Schema::create('story_continuity_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('scene_id')->nullable()->unique()->constrained('story_scenes')->cascadeOnDelete();
            $table->json('state');
            // director | manual
            $table->string('source', 16)->default('director');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_continuity_states');
    }
};
