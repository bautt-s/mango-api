<?php

namespace App\Security;

final class KeyWrapperFactory
{
    public static function forVersion(int $v): KeyWrapper
    {
        return match ($v) {
            1 => new LocalEnvWrapper(),
            // 2 => new AwsKmsWrapper(...), // later
            default => throw new \RuntimeException("Unknown key wrapper version: $v"),
        };
    }

    public static function current(): KeyWrapper
    {
        return self::forVersion(config('app.current_key_version'));
    }
}
