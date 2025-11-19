<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateAlertsJob;
use App\Models\Personal\User;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'alerts:evaluate {--user= : Evaluate alerts for a specific user ID}';

    /**
     * The console command description.
     */
    protected $description = 'Evaluate all active alerts and send notifications when conditions are met';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            // Validate user exists
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return self::FAILURE;
            }

            $this->info("Evaluating alerts for user: {$user->email} ({$userId})");
            EvaluateAlertsJob::dispatch($userId);
            $this->info("Alert evaluation job dispatched for user {$userId}");
        } else {
            $this->info("Evaluating alerts for all active users...");
            EvaluateAlertsJob::dispatch();
            $this->info("Alert evaluation job dispatched for all users");
        }

        return self::SUCCESS;
    }
}