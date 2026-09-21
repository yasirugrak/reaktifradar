<?php

use App\Enerjisa\Http\AuthController;
use App\Enerjisa\Http\Authenticate;
use App\Enerjisa\Http\NotificationController;
use App\Enerjisa\Http\PanelController;
use App\Enerjisa\Http\ReactiveController;
use App\Enerjisa\Http\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('enerjisa')->name('enerjisa.')->group(function () {
    Route::post('telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
    Route::view('login', 'enerjisa.auth', ['register' => false])->name('login');
    Route::view('register', 'enerjisa.auth', ['register' => true])->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
    Route::middleware(Authenticate::class)->group(function () {
        Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('notifications/telegram/connect', [NotificationController::class, 'connect'])->middleware('throttle:10,1')->name('notifications.connect');
        Route::post('notifications/telegram/disconnect', [NotificationController::class, 'disconnect'])->name('notifications.disconnect');
        Route::post('notifications', [NotificationController::class, 'save'])->name('notifications.save');
        Route::get('settings', [PanelController::class, 'settings'])->name('settings');
        Route::put('settings', [PanelController::class, 'saveSettings'])->name('settings.save');
        Route::post('connection', [PanelController::class, 'testConnection'])->middleware('throttle:10,1')->name('connection');
        Route::get('installations', [PanelController::class, 'installations'])->name('installations');
        Route::post('installations', [PanelController::class, 'syncInstallations'])->middleware('throttle:10,1')->name('installations.sync');
        Route::get('reactive/download', [ReactiveController::class, 'download'])->name('reactive.download');
        Route::post('reactive', [ReactiveController::class, 'calculate'])->middleware('throttle:10,1')->name('reactive.calculate');
        Route::get('reactive', [ReactiveController::class, 'index'])->name('reactive');
        Route::get('query', [PanelController::class, 'queryForm'])->name('query');
        Route::post('query', [PanelController::class, 'runQuery'])->middleware('throttle:10,1')->name('query.run');
        Route::get('results/{id}/download/{format}', [PanelController::class, 'download'])->whereNumber('id')->whereIn('format', ['csv', 'json'])->name('result.download');
        Route::get('results/{id}', [PanelController::class, 'result'])->whereNumber('id')->name('result');
    });
});
