<?php

namespace App\Console\Commands;

use App\Models\Configurations\Transaction;
use App\Models\System\WhatsappMessage;
use Illuminate\Console\Command;

class BackfillBlindIndexes extends Command
{
    protected $signature = 'bi:backfill {--chunk=500}';

    public function handle()
    {
        $chunk = (int)$this->option('chunk');
        Transaction::chunk($chunk, function ($rows) {
            foreach ($rows as $m) {
                $m->save();
            } // triggers saving() hook
        });

        WhatsappMessage::chunk($chunk, function ($rows) {
            foreach ($rows as $m) {
                $m->save();
            }
        });
    }
}
