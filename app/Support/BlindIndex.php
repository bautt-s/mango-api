<?php

namespace App\Support;

final class BlindIndex
{
    public static function hash(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') return null;
        $keyEnv = config('app.bi_key');
        if (!str_starts_with($keyEnv, 'base64:')) throw new \RuntimeException('APP_BI_KEY must be base64');
        $key = base64_decode(substr($keyEnv, 7));
        $norm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $plaintext)));
        return bin2hex(hash_hmac('sha256', $norm, $key, true)); // 64 hex chars
    }
}
