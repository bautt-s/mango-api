<?php

namespace App\Jobs;

use App\Models\Personal\User;
use App\Services\AlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AlertService $alertService): void
    {
        try {
            if ($this->userId) {
                // Evaluate alerts for a specific user
                $user = User::find($this->userId);
                if (!$user) {
                    Log::warning('User not found for alert evaluation', ['user_id' => $this->userId]);
                    return;
                }

                $this->evaluateUserAlerts($user, $alertService);
            } else {
                // Evaluate alerts for all active users with premium access
                $this->evaluateAllUsersAlerts($alertService);
            }
        } catch (\Exception $e) {
            Log::error('Failed to evaluate alerts', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }

    /**
     * Evaluate alerts for a specific user
     */
    private function evaluateUserAlerts(User $user, AlertService $alertService): void
    {
        $startTime = microtime(true);

        try {
            $triggeredAlerts = $alertService->evaluateAlertsForUser($user);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert evaluation completed for user', [
                'user_id' => $user->id,
                'triggered_count' => count($triggeredAlerts),
                'execution_time_ms' => $executionTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to evaluate alerts for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Evaluate alerts for all users with premium access
     */
    private function evaluateAllUsersAlerts(AlertService $alertService): void
    {
        $startTime = microtime(true);
        $processedCount = 0;
        $errorCount = 0;

        // Get all users with premium access (including trial)
        // TODO: Update this query when subscription system is fully implemented
        $users = User::where(function ($query) {
            $query->where('is_premium', true)
                ->orWhere('trial_ends_at', '>=', now());
        })->get();

        Log::info('Starting alert evaluation for all users', [
            'total_users' => $users->count(),
        ]);

        foreach ($users as $user) {
            try {
                $this->evaluateUserAlerts($user, $alertService);
                $processedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error evaluating alerts for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert evaluation completed for all users', [
            'total_users' => $users->count(),
            'processed' => $processedCount,
            'errors' => $errorCount,
            'execution_time_ms' => $executionTime,
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EvaluateAlertsJob failed after all retries', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
