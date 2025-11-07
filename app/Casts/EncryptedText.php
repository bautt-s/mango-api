<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use App\Services\KeyService;

class EncryptedText implements CastsAttributes
{
    private const TAG = 'enc:v1:'; // version tag for future upgrades

    public function get(Model $model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') return null;

        // 1) If not tagged, assume legacy plaintext and return as-is
        if (!is_string($value) || !str_starts_with($value, self::TAG)) {
            return $value; // legacy/plaintext support
        }

        // 2) Strip tag and decode
        $payloadB64 = substr($value, strlen(self::TAG));
        if ($payloadB64 === '' || strlen($payloadB64) < 16) {
            return null; // clearly not valid ciphertext
        }
        $raw = base64_decode($payloadB64, true);
        if ($raw === false) {
            return null; // corrupted
        }

        // 3) Split nonce + ciphertext and decrypt
        $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; // 24
        if (strlen($raw) <= $nlen) {
            return null; // corrupted
        }
        $nonce = substr($raw, 0, $nlen);
        $ct    = substr($raw, $nlen);

        $dek = app(KeyService::class)->getUserDek($this->resolveUser($model));
        $aad = $this->aad($model, $key);

        $pt = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $aad, $nonce, $dek);
        return $pt === false ? null : $pt;
    }

    public function set(Model $model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') return null;

        $dek = app(KeyService::class)->getUserDek($this->resolveUser($model));
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = $this->aad($model, $key);

        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt((string) $value, $aad, $nonce, $dek);
        return self::TAG . base64_encode($nonce . $ct);
    }

    private function aad(Model $model, string $column): string
    {
        $uid = $model->user_id ?? $model->getAttribute('user_id') ?? 'n/a';
        return $uid . '|' . $model->getTable() . '.' . $column;
    }

    private function resolveUser(Model $model)
    {
        // Prefer an actual relation if present; otherwise rely on user_id on the model
        return $model->user ?? $model;
    }
}
