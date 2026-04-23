<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AliceLinkController extends Controller
{
    public function redirectToProvider(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->alice_enabled ?? false)) {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice integration is not enabled for your account.'));
        }

        if (! config('services.alice.enabled', false)) {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice integration is globally disabled.'));
        }

        $clientId = trim((string) config('services.alice.client_id', ''));
        $redirectUri = trim((string) config('services.alice.oauth_redirect_uri', ''));
        $authorizeUrl = trim((string) config('services.alice.oauth_authorize_url', 'https://oauth.yandex.ru/authorize'));
        if ($clientId === '' || $redirectUri === '') {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice OAuth is not configured.'));
        }

        $state = Str::random(40);
        $request->session()->put('alice_oauth_state', $state);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect()->away($authorizeUrl . '?' . $query);
    }

    public function handleProviderCallback(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->alice_enabled ?? false)) {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice integration is not enabled for your account.'));
        }

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('alice_oauth_state', '');
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice OAuth state is invalid.'));
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect()->route('profile.edit')->with('alice-error', __('Alice OAuth code is missing.'));
        }

        $token = $this->exchangeCodeForToken($code);
        if ($token === null) {
            return redirect()->route('profile.edit')->with('alice-error', __('Failed to exchange Alice OAuth code.'));
        }

        $yandexUserId = $this->resolveYandexUserId($token);
        if ($yandexUserId === null) {
            return redirect()->route('profile.edit')->with('alice-error', __('Failed to resolve Yandex user id.'));
        }

        $conflictingOwner = DB::table('alice_accounts')
            ->where('yandex_user_id', $yandexUserId)
            ->whereNull('unlinked_at')
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($conflictingOwner) {
            return redirect()->route('profile.edit')->with('alice-error', __('Yandex account is already linked to another user.'));
        }

        DB::transaction(function () use ($user, $yandexUserId): void {
            DB::table('alice_accounts')
                ->where('user_id', $user->id)
                ->whereNull('unlinked_at')
                ->where('yandex_user_id', '!=', $yandexUserId)
                ->update([
                    'unlinked_at' => now(),
                    'updated_at' => now(),
                ]);

            $existing = DB::table('alice_accounts')
                ->where('yandex_user_id', $yandexUserId)
                ->first(['id']);

            if ($existing) {
                DB::table('alice_accounts')
                    ->where('id', $existing->id)
                    ->update([
                        'user_id' => $user->id,
                        'unlinked_at' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('alice_accounts')->insert([
                    'user_id' => $user->id,
                    'yandex_user_id' => $yandexUserId,
                    'unlinked_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('profile.edit')->with('alice-status', __('Alice account linked.'));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('profile.edit');
        }

        DB::table('alice_accounts')
            ->where('user_id', $user->id)
            ->whereNull('unlinked_at')
            ->update([
                'unlinked_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->route('profile.edit')->with('alice-status', __('Alice account disconnected.'));
    }

    private function exchangeCodeForToken(string $code): ?string
    {
        $tokenUrl = trim((string) config('services.alice.oauth_token_url', 'https://oauth.yandex.ru/token'));
        $clientId = trim((string) config('services.alice.client_id', ''));
        $clientSecret = trim((string) config('services.alice.client_secret', ''));
        $redirectUri = trim((string) config('services.alice.oauth_redirect_uri', ''));

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            return null;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post($tokenUrl, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->ok()) {
            return null;
        }

        $accessToken = trim((string) $response->json('access_token', ''));
        return $accessToken !== '' ? $accessToken : null;
    }

    private function resolveYandexUserId(string $accessToken): ?string
    {
        $userInfoUrl = trim((string) config('services.alice.oauth_userinfo_url', 'https://login.yandex.ru/info'));
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get($userInfoUrl, ['format' => 'json']);

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        foreach (['id', 'default_uid', 'psuid'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
