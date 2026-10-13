<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;
use App\Http\Controllers\AngelOneConnectionController;

final class WebAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($credentials['email']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }


    public function logout(
        Request $request,
        AngelOneConnectionController $angelOneController
    ): RedirectResponse {
        // Attempt to disconnect Angel One before logging out.
        try {
            $angelOneController->disconnect($request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        // Always log out the Laravel user.
        Auth::guard('web')->logout();

        // Invalidate the session and regenerate the CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

}
