<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys that would otherwise be circular at table-creation time.
     */
    public function up(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->foreign('cover_asset_id')->references('id')->on('story_assets')->nullOnDelete();
        });

        Schema::table('story_characters', function (Blueprint $table) {
            $table->foreign('voice_profile_id')->references('id')->on('story_voice_profiles')->nullOnDelete();
            $table->foreign('reference_asset_id')->references('id')->on('story_assets')->nullOnDelete();
        });

        Schema::table('story_locations', function (Blueprint $table) {
            $table->foreign('reference_asset_id')->references('id')->on('story_assets')->nullOnDelete();
        });

        Schema::table('story_voice_profiles', function (Blueprint $table) {
            $table->foreign('preview_audio_id')->references('id')->on('story_audio_assets')->nullOnDelete();
        });

        Schema::table('story_scenes', function (Blueprint $table) {
            $table->foreign('music_audio_id')->references('id')->on('story_audio_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('story_scenes', fn (Blueprint $table) => $table->dropForeign(['music_audio_id']));
        Schema::table('story_voice_profiles', fn (Blueprint $table) => $table->dropForeign(['preview_audio_id']));
        Schema::table('story_locations', fn (Blueprint $table) => $table->dropForeign(['reference_asset_id']));
        Schema::table('story_characters', function (Blueprint $table) {
            $table->dropForeign(['voice_profile_id']);
            $table->dropForeign(['reference_asset_id']);
        });
        Schema::table('story_projects', fn (Blueprint $table) => $table->dropForeign(['cover_asset_id']));
    }
};
