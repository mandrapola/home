<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AliceOAuthProviderController extends Controller
{
    public function authorize(Request $request): View|RedirectResponse
    {
        $validated = $this->validateAuthorizeRequest($request);
        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login');
        }

        if (! (bool) ($request->user()->alice_enabled ?? false)) {
            return $this->redirectAuthorizeError($validated, 'access_denied', 'Alice integration is disabled for this account.');
        }

        return view('oauth.alice-authorize', [
            'oauth' => $validated,
            'appName' => config('app.name', 'Home Aidvor'),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $this->validateAuthorizeRequest($request);
        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! (bool) ($user->alice_enabled ?? false)) {
            return $this->redirectAuthorizeError($validated, 'access_denied', 'Alice integration is disabled for this account.');
        }

        $decision = (string) $request->input('decision', 'deny');
        if ($decision !== 'allow') {
            return $this->redirectAuthorizeError($validated, 'access_denied', 'User denied access.');
        }

        $code = Str::random(64);
        DB::table('alice_oauth_auth_codes')->insert([
            'user_id' => $user->id,
            'client_id' => $validated['client_id'],
            'redirect_uri' => $validated['redirect_uri'],
            'scope' => $validated['scope'],
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds($this->authCodeTtlSeconds()),
            'consumed_at' => null,
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = ['code' => $code];
        if ($validated['state'] !== null) {
            $query['state'] = $validated['state'];
        }

        return redirect()->away($validated['redirect_uri'] . '?' . http_build_query($query));
    }

    public function token(Request $request): JsonResponse
    {
        if (! config('services.alice.enabled', false)) {
            return $this->oauthError('temporarily_unavailable', 503, 'Alice integration is disabled.');
        }

        $grantType = trim((string) $request->input('grant_type', ''));
        if ($grantType !== 'authorization_code') {
            return $this->oauthError('unsupported_grant_type', 400, 'Only authorization_code grant is supported.');
        }

        [$clientId, $clientSecret] = $this->extractClientCredentials($request);
        if (! $this->validateClientCredentials($clientId, $clientSecret)) {
            return $this->oauthError('invalid_client', 401, 'Client authentication failed.');
        }

        $code = trim((string) $request->input('code', ''));
        $redirectUri = trim((string) $request->input('redirect_uri', ''));
        if ($code === '' || $redirectUri === '') {
            return $this->oauthError('invalid_request', 400, 'code and redirect_uri are required.');
        }

        $codeRow = DB::table('alice_oauth_auth_codes')
            ->where('code_hash', hash('sha256', $code))
            ->first();

        if (! $codeRow) {
            return $this->oauthError('invalid_grant', 400, 'Authorization code is invalid.');
        }

        if ((string) $codeRow->client_id !== $clientId || (string) $codeRow->redirect_uri !== $redirectUri) {
            return $this->oauthError('invalid_grant', 400, 'Authorization code does not match client or redirect_uri.');
        }

        if ($codeRow->consumed_at !== null || $codeRow->revoked_at !== null || now()->greaterThan($codeRow->expires_at)) {
            return $this->oauthError('invalid_grant', 400, 'Authorization code is expired or already used.');
        }

        $updated = DB::table('alice_oauth_auth_codes')
            ->where('id', $codeRow->id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            return $this->oauthError('invalid_grant', 400, 'Authorization code is already used.');
        }

        $accessToken = Str::random(80);
        $expiresIn = $this->accessTokenTtlSeconds();

        DB::table('alice_oauth_access_tokens')->insert([
            'user_id' => (int) $codeRow->user_id,
            'client_id' => $clientId,
            'scope' => $codeRow->scope,
            'token_hash' => hash('sha256', $accessToken),
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'last_used_at' => null,
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ]);
    }

    private function extractClientCredentials(Request $request): array
    {
        $clientId = trim((string) $request->input('client_id', ''));
        $clientSecret = trim((string) $request->input('client_secret', ''));

        if ($clientId !== '' && $clientSecret !== '') {
            return [$clientId, $clientSecret];
        }

        $auth = trim((string) $request->header('Authorization', ''));
        if (str_starts_with(strtolower($auth), 'basic ')) {
            $decoded = base64_decode(substr($auth, 6), true);
            if (is_string($decoded) && $decoded !== '' && str_contains($decoded, ':')) {
                [$basicId, $basicSecret] = explode(':', $decoded, 2);
                $basicId = trim($basicId);
                $basicSecret = trim($basicSecret);
                if ($basicId !== '' && $basicSecret !== '') {
                    return [$basicId, $basicSecret];
                }
            }
        }

        return [$clientId, $clientSecret];
    }

    private function validateAuthorizeRequest(Request $request): array|RedirectResponse
    {
        if (! config('services.alice.enabled', false)) {
            return $this->oauthErrorRedirectless('temporarily_unavailable', 503, 'Alice integration is disabled.');
        }

        $responseType = trim((string) $request->input('response_type', ''));
        $clientId = trim((string) $request->input('client_id', ''));
        $redirectUri = trim((string) $request->input('redirect_uri', ''));
        $state = $request->input('state');
        $scope = trim((string) $request->input('scope', ''));

        if ($responseType !== 'code') {
            if ($redirectUri !== '') {
                return $this->redirectAuthorizeError([
                    'redirect_uri' => $redirectUri,
                    'state' => is_string($state) && $state !== '' ? $state : null,
                ], 'unsupported_response_type', 'Only response_type=code is supported.');
            }

            return $this->oauthErrorRedirectless('unsupported_response_type', 400, 'Only response_type=code is supported.');
        }

        if ($clientId === '' || $redirectUri === '') {
            return $this->oauthErrorRedirectless('invalid_request', 400, 'client_id and redirect_uri are required.');
        }

        if (! $this->validateClientId($clientId)) {
            return $this->redirectAuthorizeError([
                'redirect_uri' => $redirectUri,
                'state' => is_string($state) && $state !== '' ? $state : null,
            ], 'unauthorized_client', 'Client is not allowed.');
        }

        if (! $this->validateRedirectUri($redirectUri)) {
            return $this->oauthErrorRedirectless('invalid_request', 400, 'redirect_uri is not allowed.');
        }

        return [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => is_string($state) && $state !== '' ? $state : null,
            'scope' => $scope !== '' ? $scope : null,
        ];
    }

    private function validateClientId(string $clientId): bool
    {
        $configured = trim((string) config('services.alice.provider_client_id', ''));
        return $configured !== '' && hash_equals($configured, $clientId);
    }

    private function validateClientCredentials(string $clientId, string $clientSecret): bool
    {
        $configuredId = trim((string) config('services.alice.provider_client_id', ''));
        $configuredSecret = trim((string) config('services.alice.provider_client_secret', ''));

        if ($configuredId === '' || $configuredSecret === '' || $clientId === '' || $clientSecret === '') {
            return false;
        }

        return hash_equals($configuredId, $clientId) && hash_equals($configuredSecret, $clientSecret);
    }

    private function validateRedirectUri(string $redirectUri): bool
    {
        $allowed = config('services.alice.provider_redirect_uris', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        foreach ($allowed as $uri) {
            $normalized = trim((string) $uri);
            if ($normalized !== '' && hash_equals($normalized, $redirectUri)) {
                return true;
            }
        }

        return false;
    }

    private function redirectAuthorizeError(array $auth, string $error, string $description): RedirectResponse
    {
        $redirectUri = trim((string) ($auth['redirect_uri'] ?? ''));
        if ($redirectUri === '') {
            abort(400, $description);
        }

        $query = ['error' => $error];
        if (! empty($auth['state'])) {
            $query['state'] = $auth['state'];
        }
        if ($description !== '') {
            $query['error_description'] = $description;
        }

        return redirect()->away($redirectUri . '?' . http_build_query($query));
    }

    private function oauthError(string $error, int $status, string $description = ''): JsonResponse
    {
        $payload = ['error' => $error];
        if ($description !== '') {
            $payload['error_description'] = $description;
        }

        return response()->json($payload, $status);
    }

    private function oauthErrorRedirectless(string $error, int $status, string $description): never
    {
        abort($status, $description ?: $error);
    }

    private function authCodeTtlSeconds(): int
    {
        return max(60, (int) config('services.alice.provider_auth_code_ttl_seconds', 300));
    }

    private function accessTokenTtlSeconds(): int
    {
        return max(60, (int) config('services.alice.provider_access_token_ttl_seconds', 86400 * 30));
    }
}
