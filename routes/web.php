<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\GameSessionController;
use App\Http\Controllers\Admin\QuestionImportController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\PublicGameController;
use App\Http\Controllers\RpgAvatarController;
use App\Http\Middleware\AdminRoleMiddleware;
use App\Http\Middleware\QuizNoCache;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicGameController::class, 'index'])->middleware(QuizNoCache::class)->block(10, 10)->name('home');
Route::get('/games', [PublicGameController::class, 'index'])->middleware(QuizNoCache::class)->block(10, 10)->name('public.index');
// Avatars contain only catalog artwork and must not read or overwrite browser sessions.
Route::get('/avatars/rpg/{profile}.svg', RpgAvatarController::class)->withoutMiddleware('web')
    ->where('profile', '[1-9][0-9]{0,9}')->name('rpg.avatar');
Route::get('/avatars/rpg/{profile}/sprite.svg', [RpgAvatarController::class, 'sprite'])->withoutMiddleware('web')
    ->where('profile', '[1-9][0-9]{0,9}')->name('rpg.sprite');
Route::prefix('watch/{session}')->name('public.sessions.')->middleware(QuizNoCache::class)->group(function (): void {
    Route::get('/', [PublicGameController::class, 'show'])->block(10, 10)->name('show');
    Route::get('/state', [PublicGameController::class, 'state'])->middleware('throttle:quiz-state')->block(10, 10)->name('state');
});
Route::redirect('/dashboard', '/admin/dashboard')->middleware('auth')->name('dashboard');

Route::prefix('play/{session}')->name('play.')->middleware(QuizNoCache::class)->group(function (): void {
    Route::get('/', [PlayController::class, 'show'])->block(10, 10)->name('show');
    Route::post('/', [PlayController::class, 'join'])->middleware('throttle:quiz-join')->block(10, 10)->name('join');
    Route::get('/state', [PlayController::class, 'state'])->middleware('throttle:quiz-state')->block(10, 10)->name('state');
    Route::patch('/profile', [PlayController::class, 'profile'])->middleware('throttle:quiz-answer')->block(10, 10)->name('profile');
    Route::post('/ready', [PlayController::class, 'ready'])->middleware('throttle:quiz-answer')->block(10, 10)->name('ready');
    Route::post('/answers', [PlayController::class, 'answer'])->middleware('throttle:quiz-answer')->block(10, 10)->name('answer');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', AdminRoleMiddleware::class, QuizNoCache::class])
    ->group(function (): void {
        Route::redirect('/', '/admin/dashboard')->name('root');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('games', GameController::class);
        Route::get('/games/{game}/questions/template', [QuestionImportController::class, 'template'])->name('games.questions.template');
        Route::post('/games/{game}/questions/import', [QuestionImportController::class, 'store'])->middleware('throttle:10,1')->name('games.questions.import');
        Route::delete('/games/{game}/questions/{question}', [QuestionImportController::class, 'destroy'])->name('games.questions.destroy');
        Route::post('/games/{game}/sessions', [GameSessionController::class, 'store'])->name('games.sessions.store');
        Route::get('/sessions', [GameSessionController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/{session}', [GameSessionController::class, 'show'])->name('sessions.show');
        Route::get('/sessions/{session}/state', [GameSessionController::class, 'state'])->middleware('throttle:quiz-state')->block(10, 10)->name('sessions.state');
        Route::post('/sessions/{session}/start', [GameSessionController::class, 'start'])->name('sessions.start');
        Route::post('/sessions/{session}/cancel', [GameSessionController::class, 'cancel'])->name('sessions.cancel');
        Route::get('/sessions/{session}/export', [GameSessionController::class, 'export'])->name('sessions.export');
        Route::post('/users/{user}/send-reset-password', [UserController::class, 'sendResetPasswordEmail'])
            ->middleware('throttle:3,1')
            ->name('users.send-reset-password');
        Route::resource('users', UserController::class)->except('show');
    });

require __DIR__.'/auth.php';
