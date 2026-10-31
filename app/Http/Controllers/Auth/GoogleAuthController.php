<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        if (! is_string($clientId) || $clientId === '') {
            return $this->fail('Google sign-in is not configured yet.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return $this->fail('Google sign-in could not be verified. Please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->fail('Google sign-in was cancelled. You can try again.');
        }

        try {
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => route('google.callback'),
                'grant_type' => 'authorization_code',
            ])->throw()->json();

            $profile = Http::withToken($tokenResponse['access_token'] ?? '')
                ->get('https://www.googleapis.com/oauth2/v2/userinfo')
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail('We could not reach Google. Please try again.');
        }

        $googleId = $profile['id'] ?? null;
        $email = filter_var($profile['email'] ?? null, FILTER_VALIDATE_EMAIL) ? strtolower($profile['email']) : null;
        $name = trim((string) ($profile['name'] ?? ''));

        if (! is_string($googleId) || $googleId === '' || $email === null || ! ($profile['verified_email'] ?? false)) {
            return $this->fail('Google did not provide a verified email address. Please try another sign-in method.');
        }

        $user = User::query()->where('google_id', $googleId)->first();
        if ($user === null) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user !== null && $user->google_id !== null && $user->google_id !== $googleId) {
                return $this->fail('That email is already linked to a different Google account.');
            }

            if ($user === null) {
                $user = User::create([
                    'name' => $name !== '' ? $name : Str::before($email, '@'),
                    'email' => $email,
                    'password' => Str::random(64),
                    'email_verified_at' => now(),
                    'google_id' => $googleId,
                ]);
            } else {
                $user->forceFill(['google_id' => $googleId, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login'])->with('google_auth_error', $message);
    }
}
