<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned visual/packaged outputs. Rows are never overwritten:
     * each regeneration inserts a new version for the same slot.
     */
    public function up(): void
    {
        Schema::create('story_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('scene_id')->nullable()->constrained('story_scenes')->nullOnDelete();
            $table->foreignUuid('character_id')->nullable()->constrained('story_characters')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('story_locations')->nullOnDelete();
            $table->foreignUuid('generation_job_id')->nullable()->constrained('story_generation_jobs')->nullOnDelete();
            $table->string('type', 32);
            // Deterministic version group, e.g. "scene:{uuid}:scene_image".
            $table->string('slot', 128);
            $table->unsignedInteger('version');
            $table->boolean('selected')->default(false);
            $table->string('status', 16)->default('pending');
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->text('prompt')->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('generation_time_ms')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slot', 'version']);
            $table->index(['slot', 'selected']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_assets');
    }
};
