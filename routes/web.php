<?php

use App\Enerjisa\Http\TelegramWebhookController;
use App\Http\Controllers\CallbackRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
require __DIR__.'/enerjisa.php';

Route::post('/iletisim/geri-arama', CallbackRequestController::class)
    ->middleware('throttle:3,10')->name('callback.store');

// Existing bot configuration keeps working until the webhook is updated.
Route::post('/enerjisa/telegram/webhook', TelegramWebhookController::class);
Route::get('/enerjisa/{path?}', function (Request $request, string $path = '') {
    return redirect('/panel'.($path !== '' ? '/'.$path : '').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
})->where('path', '.*');
