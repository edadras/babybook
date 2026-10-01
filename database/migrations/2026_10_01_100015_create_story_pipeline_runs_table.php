<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_pipeline_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('status', 16)->default('running');
            $table->string('current_stage', 32)->nullable();
            // {"analyze": {"status": "done", "done": 1, "total": 1}, ...}
            $table->json('stage_states')->nullable();
            $table->string('batch_id', 64)->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->timestamp('cost_confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_pipeline_runs');
    }
};
