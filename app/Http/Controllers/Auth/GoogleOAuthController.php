<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GoogleOAuthController extends Controller
{
    /**
     * Kick off Google OAuth. Accepts ?intent=subscribe&plan=pro&cycle=yearly
     * to preserve the pre-auth intent through the callback.
     */
    public function redirect(Request $request): SymfonyRedirectResponse|SymfonyResponse
    {
        $intent = $request->query('intent');
        $plan = $request->query('plan');
        $cycle = $request->query('cycle');

        if ($intent) {
            session([
                'oauth_intent' => [
                    'intent' => $intent,
                    'plan' => $plan,
                    'cycle' => $cycle,
                    'at' => now()->toIso8601String(),
                ],
            ]);
        }

        if (empty(config('services.google.client_id'))) {
            return redirect()
                ->route('login')
                ->withErrors(['google' => 'La connexion Google n’est pas configurée (GOOGLE_CLIENT_ID manquant).']);
        }

        $response = Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();

        if ($request->header('X-Inertia')) {
            return Inertia::location($response->getTargetUrl());
        }

        return $response;
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $oauthUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect()
                ->route('login')
                ->withErrors(['google' => 'Échec de l’authentification Google: '.$e->getMessage()]);
        }

        $email = strtolower(trim((string) $oauthUser->getEmail()));

        if ($email === '') {
            return redirect()
                ->route('login')
                ->withErrors(['google' => 'Aucun e-mail n’a été retourné par Google.']);
        }

        // 1) Existing Google link — 2) same person already registered (password / other) by verified e-mail.
        // Never use `orWhere` alone: it breaks grouping and can miss case-variant emails in the DB.
        $user = User::query()->where('google_id', $oauthUser->getId())->first();

        if (! $user) {
            $user = User::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $oauthUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $oauthUser->getId(),
                'avatar_url' => $oauthUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => null,
                'last_login_at' => now(),
            ]);
        } else {
            $updates = [
                'google_id' => $oauthUser->getId(),
                'avatar_url' => $oauthUser->getAvatar() ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?: now(),
                'last_login_at' => now(),
            ];
            if (! filled($user->name)) {
                $updates['name'] = $oauthUser->getName() ?: Str::before($email, '@');
            }

            $user->forceFill($updates)->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return $this->dispatchAfterLogin($request);
    }

    /**
     * Decide where to send the user after a successful login:
     *   - no company yet             → onboarding
     *   - pending "subscribe" intent → billing checkout
     *   - else                       → intended()/dashboard
     */
    protected function dispatchAfterLogin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $hasCompany = $user->companies()->whereNull('company_users.revoked_at')->exists();

        if (! $hasCompany) {
            return redirect()->route('onboarding.company');
        }

        $intent = session()->pull('oauth_intent');
        if (is_array($intent) && ($intent['intent'] ?? null) === 'subscribe' && ! empty($intent['plan'])) {
            return redirect()->route('billing.checkout', [
                'plan' => $intent['plan'],
                'cycle' => $intent['cycle'] ?? 'monthly',
            ]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
