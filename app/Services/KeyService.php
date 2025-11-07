<?php

namespace App\Services;

use App\Models\Personal\User;
use App\Models\System\UserKey;
use App\Security\KeyWrapperFactory;
use Illuminate\Support\Str;

class KeyService
{
    /** Cache unwrapped DEK per request */
    private array $cache = [];

    public function getUserDek(User $user): string
    {
        $id = (string)$user->id;
        if (isset($this->cache[$id])) return $this->cache[$id];

        $uk = UserKey::where('user_id', $user->id)->first();
        if (!$uk) {
            // bootstrap: create a fresh DEK and wrap
            $dek = random_bytes(32);
            $wrapper = KeyWrapperFactory::current();
            $wrapped = $wrapper->wrap($dek);
            $uk = new UserKey([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'dek_wrapped' => $wrapped,
                'version' => $wrapper->version(),
                'rotated_at' => now(),
            ]);
            $uk->save();
            return $this->cache[$id] = $dek;
        }

        $wrapper = KeyWrapperFactory::forVersion($uk->version);
        return $this->cache[$id] = $wrapper->unwrap($uk->dek_wrapped);
    }
}
