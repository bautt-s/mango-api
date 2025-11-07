<?php

namespace App\Security;

interface KeyWrapper {
    public function version(): int;
    public function wrap(string $dek): string;    // returns ciphertext/base64
    public function unwrap(string $wrapped): string; // returns raw dek
}
