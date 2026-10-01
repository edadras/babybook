<?php

use App\Http\Controllers\StoryStudio\StoryProjectController;
use Illuminate\Support\Facades\Route;

/*
| AI Story Studio routes. Prefix, name ("story-studio.") and middleware are
| applied by StoryStudioServiceProvider.
*/

Route::get('/', [StoryProjectController::class, 'index'])->name('index');

Route::resource('projects', StoryProjectController::class)->except(['index']);
Route::get('projects/{project}/progress', [StoryProjectController::class, 'progress'])->name('projects.progress');
