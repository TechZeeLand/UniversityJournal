<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Settings;

/** "Sign in with Google" (OAuth 2.0 authorisation code flow). The client id and secret are set in the Tech portal. */
final class GoogleAuth
{
    public static function redirectUri(): string { return abs_url('/auth/google/callback'); }

    public static function configured(): bool
    {
        return Settings::get('reader_google', '0') === '1'
            && trim((string) Settings::get('google_client_id', '')) !== ''
            && trim((string) Settings::get('google_client_secret', '')) !== '';
    }

    public static function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => trim((string) Settings::get('google_client_id', '')),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Swap the code for the person's Google profile.
     * @return array{sub:string,email:string,name:string}|null null on any failure
     */
    public static function profile(string $code): ?array
    {
        $tok = self::http('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => trim((string) Settings::get('google_client_id', '')),
            'client_secret' => trim((string) Settings::get('google_client_secret', '')),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        $access = is_array($tok) ? (string) ($tok['access_token'] ?? '') : '';
        if ($access === '') return null;
        $me = self::http('https://openidconnect.googleapis.com/v1/userinfo', null, $access);
        if (!is_array($me) || empty($me['sub']) || empty($me['email']) || ($me['email_verified'] ?? false) !== true) return null;
        return ['sub' => substr((string) $me['sub'], 0, 64), 'email' => mb_substr((string) $me['email'], 0, 190), 'name' => trim((string) ($me['name'] ?? '')) ?: (string) strstr((string) $me['email'], '@', true)];
    }

    private static function http(string $url, ?array $post, ?string $bearer = null): ?array
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false];
        if ($post !== null) { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($post); }
        if ($bearer !== null) $opts[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $bearer];
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($res) || $code !== 200) return null;
        $j = json_decode($res, true);
        return is_array($j) ? $j : null;
    }
}
