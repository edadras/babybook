<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // The author's text. Never modified by the pipeline.
            $table->longText('original_text');
            $table->string('language', 8)->default('fa');
            $table->string('target_age', 8);
            $table->string('category', 32);
            $table->string('visual_style', 32);
            $table->text('custom_visual_instructions')->nullable();
            $table->string('narration_style', 32);
            $table->string('aspect_ratio', 5)->default('16:9');
            $table->string('resolution', 6)->default('1080p');
            $table->string('mode', 16)->default('manual');
            $table->string('status', 32)->default('draft')->index();
            $table->string('current_stage', 32)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('story_metadata')->nullable();
            $table->json('style_bible')->nullable();
            $table->json('safety_report')->nullable();
            $table->timestamp('safety_acknowledged_at')->nullable();
            // FK added in a later migration (story_assets does not exist yet).
            $table->uuid('cover_asset_id')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_projects');
    }
};
