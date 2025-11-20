<?php

namespace App\Http\Middleware;

use App\Services\Features\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    protected FeatureGateService $featureGateService;

    public function __construct(FeatureGateService $featureGateService)
    {
        $this->featureGateService = $featureGateService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $featureSlug
     * @param  bool  $consumeQuota  Whether to consume quota (default: false for read operations)
     */
    public function handle(Request $request, Closure $next, string $featureSlug, bool $consumeQuota = false): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'errors' => null,
            ], 401);
        }

        // Check if user has access to the feature
        if (!$this->featureGateService->userHasFeature($user, $featureSlug)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta funcionalidad requiere una suscripción premium.',
                'data' => [
                    'feature' => $featureSlug,
                    'upgrade_url' => '/subscription/plans', // Adjust to your frontend route
                ],
                'errors' => null,
            ], 403);
        }

        // For quota-based features, check quota
        if ($consumeQuota) {
            if (!$this->featureGateService->checkQuota($user, $featureSlug)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado el límite de uso para esta funcionalidad este mes.',
                    'data' => [
                        'feature' => $featureSlug,
                        'upgrade_url' => '/subscription/plans',
                    ],
                    'errors' => null,
                ], 403);
            }

            // Consume quota
            if (!$this->featureGateService->consumeQuota($user, $featureSlug)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar la solicitud. Has alcanzado el límite de uso.',
                    'data' => [
                        'feature' => $featureSlug,
                    ],
                    'errors' => null,
                ], 403);
            }
        }

        return $next($request);
    }
}