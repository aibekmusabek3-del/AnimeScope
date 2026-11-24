<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnimeController;

Route::get('/', fn () => view('app'));
Route::prefix('api')->group(function () {
    Route::get('/anime', [AnimeController::class, 'index']);
    Route::get('/anime/{id}', [AnimeController::class, 'show'])->whereNumber('id');
});

Route::get('/friends', fn () => view('app'));
Route::prefix('api/social')->controller(\App\Http\Controllers\WatchController::class)->group(function(){
    Route::get('/me','me');
    Route::post('/register','register')->middleware('throttle:5,1');
    Route::post('/login','login')->middleware('throttle:10,1');
    Route::post('/logout','logout');
    Route::get('/dashboard','dashboard');
    Route::post('/friends','add')->middleware('throttle:20,1');
    Route::post('/friends/{id}/decision','friendDecision')->whereNumber('id');
    Route::post('/rooms','invite')->middleware('throttle:10,1');
    Route::get('/rooms/{id}','state')->whereNumber('id');
    Route::post('/rooms/{id}/decision','decision')->whereNumber('id');
    Route::post('/rooms/{id}/control','control')->whereNumber('id');
    Route::post('/rooms/{id}/close','close')->whereNumber('id');
});
