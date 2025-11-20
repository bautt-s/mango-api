<?php

namespace App\Services\Features;

use App\Models\Features\Feature;
use App\Models\Features\FeatureUsage;
use App\Models\Personal\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeatureGateService
{
    /**
     * Check if user has access to a feature (binary or quota)
     */
    public function userHasFeature(User $user, string $featureSlug): bool
    {
        // Admins have access to everything
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is premium (or on trial)
        if (!$this->userHasPremiumAccess($user)) {
            // Check if feature is available on free plan
            return $this->isFeatureAvailableOnFreePlan($featureSlug);
        }

        // Get user's active subscription
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            return $this->isFeatureAvailableOnFreePlan($featureSlug);
        }

        // Check if plan has the feature enabled
        return $subscription->plan->hasFeature($featureSlug);
    }

    /**
     * Check if user has quota remaining for a quota-based feature
     */
    public function checkQuota(User $user, string $featureSlug): bool
    {
        // Admins have unlimited quota
        if ($user->isAdmin()) {
            return true;
        }

        $feature = Feature::where('slug', $featureSlug)->first();
        if (!$feature) {
            return false;
        }

        // Binary features don't have quota
        if ($feature->isBinary()) {
            return $this->userHasFeature($user, $featureSlug);
        }

        // Get quota limit
        $quota = $this->getFeatureQuota($user, $featureSlug);
        if ($quota === null) {
            // Unlimited quota (premium features often have this)
            return true;
        }

        // Get current usage
        $used = $this->getCurrentUsage($user, $feature);

        return $used < $quota;
    }

    /**
     * Consume quota for a feature (increment usage)
     * Returns true if quota was successfully consumed, false if over limit
     */
    public function consumeQuota(User $user, string $featureSlug, int $amount = 1): bool
    {
        // Admins have unlimited quota
        if ($user->isAdmin()) {
            return true;
        }

        $feature = Feature::where('slug', $featureSlug)->first();
        if (!$feature) {
            Log::warning('Attempted to consume quota for non-existent feature', [
                'user_id' => $user->id,
                'feature_slug' => $featureSlug,
            ]);
            return false;
        }

        // Binary features don't consume quota
        if ($feature->isBinary()) {
            return $this->userHasFeature($user, $featureSlug);
        }

        DB::beginTransaction();
        try {
            $periodYm = $this->getCurrentPeriod();

            // Get or create usage record
            $usage = FeatureUsage::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'feature_id' => $feature->id,
                    'period_ym' => $periodYm,
                ],
                ['used' => 0]
            );

            // Lock the row for update
            $usage = FeatureUsage::where('user_id', $user->id)
                ->where('feature_id', $feature->id)
                ->where('period_ym', $periodYm)
                ->lockForUpdate()
                ->first();

            // Get quota limit
            $quota = $this->getFeatureQuota($user, $featureSlug);

            // Check if over quota
            if ($quota !== null && $usage->used >= $quota) {
                DB::rollBack();

                Log::info('Feature quota exceeded', [
                    'user_id' => $user->id,
                    'feature_slug' => $featureSlug,
                    'used' => $usage->used,
                    'quota' => $quota,
                ]);

                return false;
            }

            // Increment usage
            $usage->used += $amount;
            $usage->save();

            Log::channel('audit')->info('Feature quota consumed', [
                'user_id' => $user->id,
                'feature_slug' => $featureSlug,
                'amount' => $amount,
                'new_usage' => $usage->used,
                'quota' => $quota,
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error consuming feature quota', [
                'user_id' => $user->id,
                'feature_slug' => $featureSlug,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reset monthly quotas for a user (called on billing cycle)
     */
    public function resetMonthlyQuotas(User $user): void
    {
        $currentPeriod = $this->getCurrentPeriod();

        // Delete old usage records (keep last 3 months for history)
        $cutoffPeriod = Carbon::now()->subMonths(3)->format('Y-m');

        FeatureUsage::where('user_id', $user->id)
            ->where('period_ym', '<', $cutoffPeriod)
            ->delete();

        Log::info('Monthly quotas reset for user', [
            'user_id' => $user->id,
            'period' => $currentPeriod,
        ]);
    }

    /**
     * Get feature usage statistics for user
     */
    public function getFeatureUsage(User $user): array
    {
        $subscription = $user->activeSubscription;
        $plan = $subscription?->plan;

        if (!$plan) {
            // Return free plan features
            return $this->getFreePlanFeatures($user);
        }

        $features = $plan->features;
        $periodYm = $this->getCurrentPeriod();
        $result = [];

        foreach ($features as $feature) {
            $featureData = [
                'slug' => $feature->slug,
                'name' => $feature->description ?? $feature->slug,
                'kind' => $feature->kind,
                'enabled' => $feature->pivot->enabled,
            ];

            if ($feature->isQuota()) {
                $quota = $feature->pivot->quota_override ?? $feature->default_quota;
                $usage = $this->getCurrentUsage($user, $feature);

                $featureData['quota'] = $quota;
                $featureData['used'] = $usage;
                $featureData['remaining'] = $quota !== null ? max(0, $quota - $usage) : null;
                $featureData['percentage_used'] = $quota !== null && $quota > 0
                    ? round(($usage / $quota) * 100, 2)
                    : 0;
            }

            $result[] = $featureData;
        }

        return $result;
    }

    /**
     * Get quota limit for a specific feature
     */
    public function getFeatureQuota(User $user, string $featureSlug): ?int
    {
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            // Check free plan
            $feature = Feature::where('slug', $featureSlug)->first();
            return $feature?->default_quota;
        }

        $feature = $subscription->plan->features()
            ->where('slug', $featureSlug)
            ->first();

        if (!$feature) {
            return 0;
        }

        // Priority: quota_override > default_quota > null (unlimited)
        return $feature->pivot->quota_override ?? $feature->default_quota;
    }

    // ==================== Private Helper Methods ====================

    /**
     * Check if user has premium access (subscription or trial)
     */
    private function userHasPremiumAccess(User $user): bool
    {
        return $user->isPremium() || $user->isOnTrial();
    }

    /**
     * Check if a feature is available on the free plan
     */
    private function isFeatureAvailableOnFreePlan(string $featureSlug): bool
    {
        // Get free plan (assuming code 'free')
        $freePlan = \App\Models\Subscriptions\Plan::where('code', 'free')->first();

        if (!$freePlan) {
            return false;
        }

        return $freePlan->hasFeature($featureSlug);
    }

    /**
     * Get current usage for a feature
     */
    private function getCurrentUsage(User $user, Feature $feature): int
    {
        $periodYm = $this->getCurrentPeriod();

        $usage = FeatureUsage::where('user_id', $user->id)
            ->where('feature_id', $feature->id)
            ->where('period_ym', $periodYm)
            ->first();

        return $usage?->used ?? 0;
    }

    /**
     * Get current period string (YYYY-MM)
     */
    private function getCurrentPeriod(): string
    {
        return Carbon::now()->format('Y-m');
    }

    /**
     * Get free plan features
     */
    private function getFreePlanFeatures(User $user): array
    {
        $freePlan = \App\Models\Subscriptions\Plan::where('code', 'free')->first();

        if (!$freePlan) {
            return [];
        }

        $features = $freePlan->features;
        $periodYm = $this->getCurrentPeriod();
        $result = [];

        foreach ($features as $feature) {
            if (!$feature->pivot->enabled) {
                continue;
            }

            $featureData = [
                'slug' => $feature->slug,
                'name' => $feature->description ?? $feature->slug,
                'kind' => $feature->kind,
                'enabled' => true,
            ];

            if ($feature->isQuota()) {
                $quota = $feature->pivot->quota_override ?? $feature->default_quota;
                $usage = $this->getCurrentUsage($user, $feature);

                $featureData['quota'] = $quota;
                $featureData['used'] = $usage;
                $featureData['remaining'] = $quota !== null ? max(0, $quota - $usage) : null;
                $featureData['percentage_used'] = $quota !== null && $quota > 0
                    ? round(($usage / $quota) * 100, 2)
                    : 0;
            }

            $result[] = $featureData;
        }

        return $result;
    }
}
