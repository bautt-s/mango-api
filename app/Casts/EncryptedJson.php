<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use App\Services\KeyService;

class EncryptedJson implements CastsAttributes
{
    private const TAG = 'enc:v1:';
    
    public function get(Model $model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') return null;
        
        if (!is_string($value) || !str_starts_with($value, self::TAG)) {
            // Legacy plaintext JSON or real JSON – try to decode gracefully
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        
        $raw = base64_decode(substr($value, strlen(self::TAG)), true);
        if ($raw === false) return null;

        $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($raw) <= $nlen) return null;
        
        $nonce = substr($raw, 0, $nlen);
        $ct    = substr($raw, $nlen);

        $dek = app(KeyService::class)->getUserDek($model->user ?? $model);
        $aad = ($model->user_id ?? 'n/a') . '|' . $model->getTable() . '.' . $key;

        $pt = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $aad, $nonce, $dek);
        if ($pt === false) return null;

        $decoded = json_decode($pt, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function set(Model $model, string $key, $value, array $attributes)
    {
        // CRITICAL FIX: Handle null and empty values
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        
        // Ensure value is encodable
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }
        
        $dek = app(KeyService::class)->getUserDek($model->user ?? $model);

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = ($model->user_id ?? 'n/a') . '|' . $model->getTable() . '.' . $key;
        $ct  = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($json, $aad, $nonce, $dek);

        return self::TAG . base64_encode($nonce . $ct);
    }
}