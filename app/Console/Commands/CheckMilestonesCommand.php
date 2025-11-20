<?php

namespace App\Console\Commands;

use App\Jobs\CheckMilestonesJob;
use Illuminate\Console\Command;

class CheckMilestonesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'milestones:check 
                            {user? : The ID of a specific user to check}
                            {--queue : Queue the job instead of running immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and award milestones for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user');
        $useQueue = $this->option('queue');

        if ($userId) {
            $this->info("Checking milestones for user ID: {$userId}...");
        } else {
            $this->info('Checking milestones for all users...');
        }

        if ($useQueue) {
            CheckMilestonesJob::dispatch($userId);
            $this->info('Job queued successfully.');
        } else {
            CheckMilestonesJob::dispatchSync($userId);
            $this->info('Milestone check completed.');
        }

        return self::SUCCESS;
    }
}
