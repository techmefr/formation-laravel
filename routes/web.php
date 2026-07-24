<?php

use Functional\Authentication\Http\Controllers\Auth\AuthenticatedSessionController;
use Functional\Authentication\Http\Controllers\Auth\NewPasswordController;
use Functional\Authentication\Http\Controllers\Auth\PasswordResetLinkController;
use Functional\Authentication\Http\Controllers\Auth\RegisteredUserController;
use Functional\Calendar\Http\Controllers\CalendarController;
use Functional\Seances\Http\Controllers\InscriptionController;
use Functional\Seances\Http\Controllers\ParticipantController;
use Functional\Seances\Http\Controllers\SeanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('seances.index')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');

    Route::post('/seances', [SeanceController::class, 'store'])->name('seances.store');
    Route::put('/seances/{seance}', [SeanceController::class, 'update'])->name('seances.update');
    Route::delete('/seances/{seance}', [SeanceController::class, 'destroy'])->name('seances.destroy');
    Route::post('/seances/{seance}/cancel', [SeanceController::class, 'cancel'])->name('seances.cancel');

    Route::post('/seances/{seance}/inscription', [InscriptionController::class, 'store'])->name('seances.inscription.store');
    Route::delete('/seances/{seance}/inscription', [InscriptionController::class, 'destroy'])->name('seances.inscription.destroy');

    Route::post('/seances/{seance}/participants', [ParticipantController::class, 'store'])->name('seances.participants.store');
    Route::delete('/seances/{seance}/participants/{user}', [ParticipantController::class, 'destroy'])->name('seances.participants.destroy');
});
