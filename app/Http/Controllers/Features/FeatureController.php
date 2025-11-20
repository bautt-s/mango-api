<?php

namespace App\Http\Controllers\Features;

use App\Http\Controllers\Controller;
use App\Services\Features\FeatureGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    protected FeatureGateService $featureGateService;

    public function __construct(FeatureGateService $featureGateService)
    {
        $this->featureGateService = $featureGateService;
    }

    /**
     * Get all features available to the authenticated user
     * GET /api/v1/features
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $features = $this->featureGateService->getFeatureUsage($user);

            return $this->successResponse(
                $features,
                'Funcionalidades obtenidas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Check if user has access to a specific feature
     * GET /api/v1/features/{slug}/check
     */
    public function check(Request $request, string $slug): JsonResponse
    {
        try {
            $user = $request->user();
            $hasAccess = $this->featureGateService->userHasFeature($user, $slug);

            $data = [
                'feature' => $slug,
                'has_access' => $hasAccess,
            ];

            // If it's a quota feature, include quota info
            $feature = \App\Models\Features\Feature::where('slug', $slug)->first();

            if ($feature && $feature->isQuota()) {
                $quota = $this->featureGateService->getFeatureQuota($user, $slug);
                $usage = $this->featureGateService->getFeatureUsage($user);

                $featureUsage = collect($usage)->firstWhere('slug', $slug);

                $data['quota'] = $quota;
                $data['used'] = $featureUsage['used'] ?? 0;
                $data['remaining'] = $featureUsage['remaining'] ?? null;
                $data['can_use'] = $this->featureGateService->checkQuota($user, $slug);
            }

            return $this->successResponse(
                $data,
                $hasAccess
                    ? 'Tienes acceso a esta funcionalidad.'
                    : 'No tienes acceso a esta funcionalidad.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get quota information for all quota-based features
     * GET /api/v1/features/quotas
     */
    public function quotas(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $allFeatures = $this->featureGateService->getFeatureUsage($user);

            // Filter only quota features
            $quotaFeatures = array_filter($allFeatures, function ($feature) {
                return $feature['kind'] === 'quota';
            });

            return $this->successResponse(
                array_values($quotaFeatures),
                'Cuotas de funcionalidades obtenidas exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Get available plans with their features
     * GET /api/v1/features/plans
     */
    public function plans(Request $request): JsonResponse
    {
        try {
            $plans = \App\Models\Subscriptions\Plan::active()
                ->with(['features' => function ($query) {
                    $query->orderBy('slug');
                }])
                ->get();

            $formattedPlans = $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'interval' => $plan->interval,
                    'price_cents' => $plan->price_cents,
                    'price' => $plan->price,
                    'currency_code' => $plan->currency_code,
                    'features' => $plan->features->map(function ($feature) {
                        return [
                            'slug' => $feature->slug,
                            'name' => $feature->description,
                            'kind' => $feature->kind,
                            'enabled' => $feature->pivot->enabled,
                            'quota' => $feature->pivot->quota_override ?? $feature->default_quota,
                        ];
                    }),
                ];
            });

            return $this->successResponse(
                $formattedPlans,
                'Planes disponibles obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}