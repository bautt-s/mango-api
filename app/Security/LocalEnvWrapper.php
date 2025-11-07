<?php

namespace App\Security;

final class LocalEnvWrapper implements KeyWrapper
{
    private string $master;

    public function __construct()
    {
        $env = config('app.master_key'); // from config/app.php
        if (!str_starts_with($env, 'base64:')) {
            throw new \RuntimeException('APP_MASTER_KEY must be base64:...');
        }
        $this->master = base64_decode(substr($env, 7));
        if (strlen($this->master) !== 32) {
            throw new \RuntimeException('APP_MASTER_KEY must decode to 32 bytes');
        }
    }

    public function version(): int
    {
        return 1;
    }

    public function wrap(string $dek): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = 'user-dek';
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($dek, $aad, $nonce, $this->master);
        return base64_encode($nonce . $ct);
    }

    public function unwrap(string $wrapped): string
    {
        $raw = base64_decode($wrapped);
        $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $nonce = substr($raw, 0, $nlen);
        $ct = substr($raw, $nlen);
        $aad = 'user-dek';
        $pt = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $aad, $nonce, $this->master);
        if ($pt === false) throw new \RuntimeException('Unable to unwrap DEK');
        return $pt;
    }
}
