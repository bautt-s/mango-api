<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configurations\PaymentMethods\SetBillingCycleRequest;
use App\Http\Requests\Configurations\PaymentMethods\StorePaymentMethodRequest;
use App\Http\Requests\Configurations\PaymentMethods\UpdatePaymentMethodRequest;
use App\Http\Resources\Configurations\PaymentMethodResource;
use App\Models\Configurations\PaymentMethod;
use App\Services\Configurations\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    protected PaymentMethodService $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * Get all payment methods for authenticated user
     * GET /api/v1/payment-methods
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $paymentMethods = $this->paymentMethodService->getPaymentMethodsForUser($user);

            return $this->successResponse(
                PaymentMethodResource::collection($paymentMethods),
                'Métodos de pago obtenidos exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Create a new payment method
     * POST /api/v1/payment-methods
     */
    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $paymentMethod = $this->paymentMethodService->createPaymentMethod($user, $request->validated());

            return $this->successResponse(
                new PaymentMethodResource($paymentMethod),
                'Método de pago creado exitosamente.',
                201
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Update an existing payment method
     * PUT /api/v1/payment-methods/{paymentMethod}
     */
    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $updated = $this->paymentMethodService->updatePaymentMethod($paymentMethod, $request->validated());

            return $this->successResponse(
                new PaymentMethodResource($updated),
                'Método de pago actualizado exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Delete a payment method
     * DELETE /api/v1/payment-methods/{paymentMethod}
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $this->paymentMethodService->deletePaymentMethod($paymentMethod);

            return $this->successResponse(
                null,
                'Método de pago eliminado exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Set a payment method as default
     * PATCH /api/v1/payment-methods/{paymentMethod}/default
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            $updated = $this->paymentMethodService->setAsDefault($paymentMethod);

            return $this->successResponse(
                new PaymentMethodResource($updated),
                'Método de pago establecido como predeterminado.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }

    /**
     * Set billing cycle for a credit card
     * PATCH /api/v1/payment-methods/{paymentMethod}/billing-cycle
     */
    public function setBillingCycle(SetBillingCycleRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== $request->user()->id) {
                return $this->unauthorizedResponse();
            }

            // Verify it's a credit card
            if (!$paymentMethod->isCreditCard()) {
                return $this->errorResponse(
                    ['payment_method' => ['El método de pago debe ser una tarjeta de crédito']],
                    'Solo las tarjetas de crédito pueden tener ciclo de facturación.',
                    400
                );
            }

            $updated = $this->paymentMethodService->setBillingCycle($paymentMethod, $request->validated());

            return $this->successResponse(
                new PaymentMethodResource($updated),
                'Ciclo de facturación configurado exitosamente.'
            );
        } catch (\Throwable $th) {
            return $this->throwableError($th);
        }
    }
}