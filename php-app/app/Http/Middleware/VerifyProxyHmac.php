<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyProxyHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $proxyId = trim((string) $request->header('X-Proxy-Id', ''));
        $timestampRaw = trim((string) $request->header('X-Timestamp', ''));
        $nonce = trim((string) $request->header('X-Nonce', ''));
        $signature = strtolower(trim((string) $request->header('X-Signature', '')));

        if ($proxyId === '' || $timestampRaw === '' || $nonce === '' || $signature === '') {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Missing HMAC headers.',
            ], 401);
        }

        if (! ctype_digit($timestampRaw)) {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Invalid timestamp format.',
            ], 401);
        }

        $timestamp = (int) $timestampRaw;
        $now = time();
        $maxSkew = max(5, (int) env('PROXY_HMAC_MAX_SKEW_SECONDS', 60));
        if (abs($now - $timestamp) > $maxSkew) {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Timestamp is outside allowed window.',
            ], 401);
        }

        $secret = $this->resolveSecretForProxy($proxyId);
        if ($secret === null || $secret === '') {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Unknown proxy id.',
            ], 401);
        }

        $body = (string) $request->getContent();
        $bodyHash = hash('sha256', $body);
        $method = strtoupper((string) $request->method());
        $path = (string) $request->getPathInfo();

        $canonical = implode("\n", [
            $method,
            $path,
            $bodyHash,
            (string) $timestamp,
            $nonce,
            $proxyId,
        ]);

        $expected = hash_hmac('sha256', $canonical, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Invalid HMAC signature.',
            ], 401);
        }

        $nonceTtl = max(10, (int) env('PROXY_HMAC_NONCE_TTL_SECONDS', 300));
        $nonceCacheKey = 'proxy_hmac_nonce:' . $proxyId . ':' . $nonce;
        $nonceAccepted = Cache::add($nonceCacheKey, 1, now()->addSeconds($nonceTtl));
        if (! $nonceAccepted) {
            return response()->json([
                'error' => 'proxy_auth_failed',
                'message' => 'Nonce already used.',
            ], 401);
        }

        return $next($request);
    }

    private function resolveSecretForProxy(string $proxyId): ?string
    {
        $pairs = trim((string) env('PROXY_HMAC_KEYS', ''));
        if ($pairs !== '') {
            $chunks = array_filter(array_map('trim', explode(',', $pairs)));
            foreach ($chunks as $chunk) {
                $parts = explode(':', $chunk, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $id = trim($parts[0]);
                $secret = trim($parts[1]);
                if ($id !== '' && $id === $proxyId) {
                    return $secret;
                }
            }
        }

        $singleId = trim((string) env('PROXY_HMAC_PROXY_ID', ''));
        $singleSecret = trim((string) env('PROXY_HMAC_SECRET', ''));
        if ($singleId !== '' && $singleSecret !== '' && $singleId === $proxyId) {
            return $singleSecret;
        }

        return null;
    }
}
