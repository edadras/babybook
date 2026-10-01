<?php

use App\Http\Controllers\StoryStudio\CharacterController;
use App\Http\Controllers\StoryStudio\FileController;
use App\Http\Controllers\StoryStudio\OutputController;
use App\Http\Controllers\StoryStudio\PipelineController;
use App\Http\Controllers\StoryStudio\PronunciationController;
use App\Http\Controllers\StoryStudio\SceneController;
use App\Http\Controllers\StoryStudio\SoundController;
use App\Http\Controllers\StoryStudio\StoryProjectController;
use App\Http\Controllers\StoryStudio\TimelineController;
use App\Http\Controllers\StoryStudio\VoiceController;
use Illuminate\Support\Facades\Route;

/*
| AI Story Studio routes. Prefix, name ("story-studio.") and middleware are
| applied by StoryStudioServiceProvider.
*/

Route::get('/', [StoryProjectController::class, 'index'])->name('index');
Route::get('files/{kind}/{id}', [FileController::class, 'show'])->name('files.show');

Route::resource('projects', StoryProjectController::class)->except(['index']);

Route::prefix('projects/{project}')->name('projects.')->group(function () {
    Route::get('progress', [StoryProjectController::class, 'progress'])->name('progress');

    // Pipeline
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('pipeline/start', [PipelineController::class, 'start'])->name('pipeline.start');
        Route::post('pipeline/analyze', [PipelineController::class, 'analyze'])->name('pipeline.analyze');
        Route::post('pipeline/pause', [PipelineController::class, 'pause'])->name('pipeline.pause');
        Route::post('pipeline/resume', [PipelineController::class, 'resume'])->name('pipeline.resume');
        Route::post('pipeline/cancel', [PipelineController::class, 'cancel'])->name('pipeline.cancel');
        Route::post('pipeline/retry', [PipelineController::class, 'retry'])->name('pipeline.retry');
        Route::post('pipeline/approve/{gate}', [PipelineController::class, 'approve'])->name('pipeline.approve')->whereIn('gate', ['script', 'characters', 'voices', 'storyboard', 'review', 'safety']);
    });
    Route::get('pipeline/estimate', [PipelineController::class, 'estimate'])->name('pipeline.estimate');

    // Characters & references
    Route::scopeBindings()->group(function () {
        Route::get('characters', [CharacterController::class, 'index'])->name('characters');
        Route::put('characters/{character}', [CharacterController::class, 'update'])->name('characters.update');
        Route::post('characters/{character}/references', [CharacterController::class, 'references'])->name('characters.references');
        Route::post('characters/{character}/refine', [CharacterController::class, 'refine'])->name('characters.refine');
        Route::post('characters/{character}/lock', [CharacterController::class, 'lock'])->name('characters.lock');
        Route::post('assets/{asset}/select', [CharacterController::class, 'selectAsset'])->name('assets.select');

        // Voices
        Route::get('voices', [VoiceController::class, 'index'])->name('voices');
        Route::post('characters/{character}/voices/design', [VoiceController::class, 'design'])->name('voices.design');
        Route::post('characters/{character}/voices/library', [VoiceController::class, 'library'])->name('voices.library');
        Route::post('characters/{character}/voices/lock', [VoiceController::class, 'lock'])->name('voices.lock');

        // Storyboard
        Route::get('storyboard', [SceneController::class, 'index'])->name('storyboard');
        Route::put('scenes/{scene}', [SceneController::class, 'update'])->name('scenes.update');
        Route::post('scenes/{scene}/image', [SceneController::class, 'image'])->name('scenes.image');
        Route::post('scenes/{scene}/narration', [SceneController::class, 'narration'])->name('scenes.narration');
        Route::post('scenes/{scene}/preview', [SceneController::class, 'preview'])->name('scenes.preview');
        Route::post('scenes/{scene}/split', [SceneController::class, 'split'])->name('scenes.split');
        Route::delete('scenes/{scene}', [SceneController::class, 'destroy'])->name('scenes.destroy');
        Route::post('scenes/{scene}/dialogues', [SceneController::class, 'storeDialogue'])->name('dialogues.store');
        Route::post('scenes/{scene}/effects', [SoundController::class, 'storeEffect'])->name('effects.store');

        // Timeline
        Route::get('timeline', [TimelineController::class, 'index'])->name('timeline');
        Route::put('timeline/{scene}', [TimelineController::class, 'update'])->name('timeline.update');
    });

    Route::post('voices/{profile}/select', [VoiceController::class, 'select'])->name('voices.select');
    Route::post('voices/{profile}/preview', [VoiceController::class, 'preview'])->name('voices.preview');
    Route::post('scenes-order', [SceneController::class, 'reorder'])->name('scenes.reorder');
    Route::put('dialogues/{dialogue}', [SceneController::class, 'updateDialogue'])->name('dialogues.update');
    Route::post('dialogues/{dialogue}/regenerate', [SceneController::class, 'regenerateDialogue'])->name('dialogues.regenerate');
    Route::delete('dialogues/{dialogue}', [SceneController::class, 'destroyDialogue'])->name('dialogues.destroy');

    // Sound design & music
    Route::get('sound', [SoundController::class, 'index'])->name('sound');
    Route::put('effects/{effect}', [SoundController::class, 'updateEffect'])->name('effects.update');
    Route::post('effects/{effect}/regenerate', [SoundController::class, 'regenerateEffect'])->name('effects.regenerate');
    Route::delete('effects/{effect}', [SoundController::class, 'destroyEffect'])->name('effects.destroy');
    Route::post('music', [SoundController::class, 'uploadMusic'])->name('music.upload');
    Route::post('music/assign', [SoundController::class, 'assignMusic'])->name('music.assign');
    Route::put('settings', [SoundController::class, 'updateSettings'])->name('settings.update');

    // Pronunciation
    Route::get('pronunciation', [PronunciationController::class, 'index'])->name('pronunciation');
    Route::post('pronunciation', [PronunciationController::class, 'store'])->name('pronunciation.store');
    Route::delete('pronunciation/{pronunciation}', [PronunciationController::class, 'destroy'])->name('pronunciation.destroy');

    // Outputs
    Route::get('render', [OutputController::class, 'render'])->name('render');
    Route::get('export', [OutputController::class, 'export'])->name('export');
    Route::get('costs', [OutputController::class, 'costs'])->name('costs');
});
