<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_characters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            // Stable key produced by the AI director, e.g. "little_rabbit".
            $table->string('key', 64);
            $table->string('name');
            $table->string('role', 64)->nullable();
            $table->boolean('is_narrator')->default(false);
            $table->boolean('is_major')->default(true);
            $table->string('age_group', 64)->nullable();
            $table->string('gender', 32)->nullable();
            $table->text('personality')->nullable();

            // Character Bible: locked visual identity.
            $table->text('visual_description')->nullable();
            $table->text('clothing')->nullable();
            $table->text('hair')->nullable();
            $table->text('face')->nullable();
            $table->json('colors')->nullable();
            $table->string('height_relation')->nullable();
            $table->text('special_features')->nullable();
            $table->boolean('character_locked')->default(false);
            $table->timestamp('locked_at')->nullable();

            $table->text('voice_description')->nullable();
            // FKs added later: story_voice_profiles / story_assets come after this table.
            $table->uuid('voice_profile_id')->nullable();
            $table->uuid('reference_asset_id')->nullable();

            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_characters');
    }
};
