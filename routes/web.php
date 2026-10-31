<?php

use App\Http\Controllers\AngelOneConnectionController;
use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');
Route::view('/about', 'pages.about')->name('about');
Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => redirect()->route('home', ['auth' => 'login']))->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.store');
    Route::get('/register', fn () => redirect()->route('home', ['auth' => 'register']))->name('register');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/api/auth/callback/google', [GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::post('/register', [WebAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function () {
        $userId = auth()->id();
        $connection = DB::table('angel_one_connections')->where('user_id', $userId)->first();
        $sessionConnection = session('angel_one.connection');

        if (
            $connection === null
            && is_array($sessionConnection)
            && is_string($sessionConnection['client_code'] ?? null)
            && is_string($sessionConnection['secrets'] ?? null)
        ) {
            DB::table('angel_one_connections')->insertOrIgnore([
                'user_id' => $userId,
                'client_code' => $sessionConnection['client_code'],
                'encrypted_secrets' => $sessionConnection['secrets'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $connection = DB::table('angel_one_connections')->where('user_id', $userId)->first();
        }

        return view('dashboard', ['angelOneConnection' => $connection]);
    })->name('dashboard');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/option-chain/angel-one/connect', [AngelOneConnectionController::class, 'connect'])
        ->middleware('throttle:5,1')
        ->name('option-chain.angel-one.connect');
    Route::get('/option-chain/angel-one/expiries', [AngelOneConnectionController::class, 'expiries'])
        ->middleware('throttle:30,1')
        ->name('option-chain.angel-one.expiries');
    Route::post('/option-chain/angel-one/subscribe', [AngelOneConnectionController::class, 'subscribe'])
        ->middleware('throttle:30,1')
        ->name('option-chain.angel-one.subscribe');
    Route::post('/option-chain/angel-one/greeks', [AngelOneConnectionController::class, 'greeks'])
        ->middleware('throttle:30,1')
        ->name('option-chain.angel-one.greeks');
    Route::post('/option-chain/angel-one/disconnect', [AngelOneConnectionController::class, 'disconnect'])
        ->name('option-chain.angel-one.disconnect');
});
