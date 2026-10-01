<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger of every external (billable) generation request.
     * Doubles as the idempotency guard and the cost source of truth.
     */
    public function up(): void
    {
        Schema::create('story_generation_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('story_projects')->cascadeOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('stage', 32);
            $table->string('operation', 64);
            $table->nullableUuidMorphs('target');
            $table->string('provider', 32);
            $table->string('model', 64)->nullable();
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('request_hash', 64)->nullable();
            $table->string('provider_request_id', 128)->nullable();
            // Redacted summaries only: never store credentials or raw binary payloads.
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('cost_category', 32)->nullable();
            $table->string('unit_type', 16)->nullable();
            $table->unsignedBigInteger('input_units')->default(0);
            $table->unsignedBigInteger('output_units')->default(0);
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'stage', 'status']);
            $table->index(['project_id', 'cost_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_generation_jobs');
    }
};
