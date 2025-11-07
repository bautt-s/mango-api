<?php

namespace App\Console\Commands;

use App\Models\System\UserKey;
use Illuminate\Console\Command;

class RewrapUserKeys extends Command
{
    protected $signature = 'keys:rewrap {--chunk=500}';

    public function handle()
    {
        $chunk = (int)$this->option('chunk');
        UserKey::query()->orderBy('user_id')->chunk($chunk, function ($rows) {
            $current = \App\Security\KeyWrapperFactory::current();
            foreach ($rows as $uk) {
                $legacy = \App\Security\KeyWrapperFactory::forVersion($uk->version);
                $dek = $legacy->unwrap($uk->dek_wrapped);
                $uk->dek_wrapped = $current->wrap($dek);
                $uk->version = $current->version();
                $uk->rotated_at = now();
                $uk->save();
            }
        });
    }
}