<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_render_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->foreignUuid('scene_id')->nullable()->constrained('story_scenes')->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            // Snapshot of timeline/settings used, so a render is reproducible.
            $table->json('input_snapshot')->nullable();
            $table->json('output_asset_ids')->nullable();
            $table->string('log_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_render_jobs');
    }
};
