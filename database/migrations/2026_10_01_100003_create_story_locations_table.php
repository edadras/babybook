<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            // Stable key produced by the AI director, e.g. "forest_edge".
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('visual_description')->nullable();
            $table->string('default_time_of_day', 32)->nullable();
            $table->uuid('reference_asset_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_locations');
    }
};
