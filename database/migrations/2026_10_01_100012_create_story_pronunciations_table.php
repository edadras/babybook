<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_pronunciations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null project_id = global dictionary shared by all projects.
            $table->foreignUuid('project_id')->nullable()->constrained('story_projects')->cascadeOnDelete();
            $table->string('word', 191);
            $table->string('pronunciation');
            $table->string('type', 32)->default('general');
            $table->boolean('whole_word')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_pronunciations');
    }
};
