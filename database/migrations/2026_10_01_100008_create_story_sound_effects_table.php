<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_sound_effects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('scene_id')->constrained('story_scenes')->cascadeOnDelete();
            $table->string('kind', 16)->default('sfx');
            $table->string('description');
            $table->text('prompt')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('start_offset_ms')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('volume', 4, 2)->default(0.6);
            $table->boolean('loop')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_sound_effects');
    }
};
