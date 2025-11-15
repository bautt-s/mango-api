<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\User\UpdateEmailRequest;
use App\Http\Requests\Personal\User\UpdateProfileRequest;
use App\Http\Resources\Personal\UserResource;
use App\Services\Personal\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Obtener el perfil del usuario autenticado
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('activeSubscription.plan');

        return $this->successResponse(
            data: new UserResource($user),
            message: 'Perfil obtenido exitosamente'
        );
    }

    /**
     * Actualizar el perfil del usuario
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->updateProfile(
                user: $request->user(),
                data: $request->validated()
            );

            return $this->successResponse(
                data: new UserResource($user->load('subscription.plan')),
                message: 'Perfil actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Actualizar el email del usuario (requiere re-verificación)
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->updateEmail(
                user: $request->user(),
                email: $request->validated('email')
            );

            return $this->successResponse(
                data: new UserResource($user),
                message: 'Email actualizado. Por favor verifica tu nuevo email.'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }

    /**
     * Obtener información de la suscripción del usuario
     */
    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscription.plan');

        if (!$user->subscription) {
            return $this->successResponse(
                data: null,
                message: 'No tienes una suscripción activa'
            );
        }

        return $this->successResponse(
            data: new UserResource($user),
            message: 'Información de suscripción obtenida exitosamente'
        );
    }

    /**
     * Desactivar la cuenta del usuario (soft delete)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $this->userService->deleteAccount($request->user());

            return $this->successResponse(
                data: null,
                message: 'Tu cuenta ha sido desactivada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->throwableError($e);
        }
    }
}