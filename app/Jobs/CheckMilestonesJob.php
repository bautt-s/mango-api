<?php

namespace App\Jobs;

use App\Models\Personal\User;
use App\Services\Personal\MilestoneService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMilestonesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public ?int $userId;

    /**
     * Create a new job instance.
     *
     * @param int|null $userId If null, check all users
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(MilestoneService $milestoneService): void
    {
        if ($this->userId) {
            // Check specific user
            $user = User::find($this->userId);
            if ($user) {
                $this->checkUserMilestones($user, $milestoneService);
            }
        } else {
            // Check all active users in batches
            User::whereNull('deleted_at')
                ->chunk(100, function ($users) use ($milestoneService) {
                    foreach ($users as $user) {
                        try {
                            $this->checkUserMilestones($user, $milestoneService);
                        } catch (\Exception $e) {
                            Log::error('Error checking milestones for user', [
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        }
    }

    /**
     * Check milestones for a specific user
     */
    private function checkUserMilestones(User $user, MilestoneService $milestoneService): void
    {
        $newlyAchieved = $milestoneService->checkMilestones($user);

        if ($newlyAchieved->isNotEmpty()) {
            Log::info('User achieved new milestones', [
                'user_id' => $user->id,
                'count' => $newlyAchieved->count(),
                'milestones' => $newlyAchieved->pluck('code')->toArray(),
            ]);

            // Here you could dispatch notifications
            // For example: dispatch(new SendMilestoneNotification($user, $newlyAchieved));
        }
    }
}
