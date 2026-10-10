<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use UserRegister\Http\RegistrationController as Controller;
use UserRegister\Http\RegistrationGuard;

Route::middleware([RegistrationGuard::class, 'guest'])->group(function (): void {
    Route::get('/', [Controller::class, 'start'])->name('start');
    Route::post('/register', [Controller::class, 'register'])->name('register');
    Route::post('/request', [Controller::class, 'requestEmail'])->name('request');
    Route::get('/sent', [Controller::class, 'sent'])->name('sent');
    Route::get('/verify/{token}', [Controller::class, 'verify'])->name('verify');
    Route::get('/complete', [Controller::class, 'form'])->name('form');
    Route::post('/complete', [Controller::class, 'complete'])->name('complete');
});
