<?php

namespace App\Services\Personal;

use App\Models\Personal\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    /**
     * Actualizar el perfil del usuario
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->fill([
                'username' => $data['username'] ?? $user->username,
                'phone' => $data['phone'] ?? $user->phone,
                'timezone' => $data['timezone'] ?? $user->timezone,
                'currency_code' => $data['currency_code'] ?? $user->currency_code,
            ]);

            $user->save();

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'changes' => $user->getChanges(),
            ]);

            return $user->fresh();
        });
    }

    /**
     * Actualizar el email del usuario y enviar verificación
     */
    public function updateEmail(User $user, string $email): User
    {
        return DB::transaction(function () use ($user, $email) {
            $user->email = $email;
            $user->email_verified_at = null;
            $user->save();

            // Enviar email de verificación
            event(new Registered($user));

            Log::info('User email updated, verification sent', [
                'user_id' => $user->id,
                'new_email' => $email,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Desactivar la cuenta del usuario (soft delete)
     */
    public function deleteAccount(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $userId = $user->id;
            $username = $user->username;

            // Soft delete del usuario (cascade automático por DB)
            $user->delete();

            Log::warning('User account deactivated', [
                'user_id' => $userId,
                'username' => $username,
            ]);

            return true;
        });
    }
}