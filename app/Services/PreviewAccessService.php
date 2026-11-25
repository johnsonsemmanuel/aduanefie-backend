<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreviewAccessService
{
    public const SETTING_KEY = 'public_access_enabled';
    private const CACHE_KEY = 'aduanefie_public_access_enabled_v1';
    public const COOKIE_NAME = 'aduanefie_preview_token';
    public const HEADER_NAME = 'X-Preview-Access';

    /**
     * Returns true ONLY when public access has been explicitly opened.
     * Defaults to false (closed) in production/staging so Aduanefie stays
     * gated until explicit approval. In automated PHPUnit runs (`testing`),
     * defaults to open unless `public_access_enabled` is explicitly set in
     * `business_settings` so unrelated feature tests do not require tokens.
     */
    public function isPublicAccessOpen(): bool
    {
        if (!Schema::hasTable('business_settings')) {
            return app()->environment('testing');
        }

        $raw = Cache::remember(self::CACHE_KEY, now()->addMinutes(2), function () {
            return DB::table('business_settings')->where('key', self::SETTING_KEY)->value('value');
        });

        if ($raw === null) {
            return app()->environment('testing');
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'open'], true);
    }

    public function issueToken(int $ttlSeconds = 2592000): string
    {
        $exp = time() + max(60, $ttlSeconds);
        $sig = $this->sign($exp);
        return "{$exp}.{$sig}";
    }

    public function verifyToken(?string $token): bool
    {
        if (!is_string($token)) {
            return false;
        }
        $trimmed = trim($token);
        if ($trimmed === '' || !str_contains($trimmed, '.')) {
            return false;
        }

        [$expPart, $sigPart] = explode('.', $trimmed, 2);
        if (!ctype_digit($expPart) || strlen($sigPart) !== 64 || !ctype_xdigit($sigPart)) {
            return false;
        }

        $exp = (int) $expPart;
        if ($exp <= time()) {
            return false;
        }

        $expectedSig = $this->sign($exp);
        return hash_equals($expectedSig, strtolower($sigPart));
    }

    private function sign(int $exp): string
    {
        $secret = (string) (config('app.key') ?: env('APP_KEY', 'aduanefie-preview-fallback-key'));
        return hash_hmac('sha256', "aduanefie-preview-v1:{$exp}", $secret);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
