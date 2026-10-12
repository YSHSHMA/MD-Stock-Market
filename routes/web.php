<?php

use App\Http\Controllers\AngelOneConnectionController;
use App\Http\Controllers\Auth\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.store');
    Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard', [
        'angelOneConnection' => session('angel_one.connection'),
    ]))->name('dashboard');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/option-chain/angel-one/connect', [AngelOneConnectionController::class, 'connect'])
        ->middleware('throttle:5,1')
        ->name('option-chain.angel-one.connect');
    Route::get('/option-chain/angel-one/expiries', [AngelOneConnectionController::class, 'expiries'])
        ->middleware('throttle:30,1')
        ->name('option-chain.angel-one.expiries');
    Route::post('/option-chain/angel-one/greeks', [AngelOneConnectionController::class, 'greeks'])
        ->middleware('throttle:30,1')
        ->name('option-chain.angel-one.greeks');
    Route::post('/option-chain/angel-one/disconnect', [AngelOneConnectionController::class, 'disconnect'])
        ->name('option-chain.angel-one.disconnect');
});
