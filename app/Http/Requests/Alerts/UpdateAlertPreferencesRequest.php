<?php

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => 'sometimes|boolean',
            'whatsapp_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
            
            'type_preferences' => 'sometimes|array',
            'type_preferences.*.email' => 'boolean',
            'type_preferences.*.whatsapp' => 'boolean',
            'type_preferences.*.in_app' => 'boolean',
            
            'quiet_hours' => 'sometimes|array',
            'quiet_hours.enabled' => 'required_with:quiet_hours|boolean',
            'quiet_hours.from' => 'required_if:quiet_hours.enabled,true|date_format:H:i',
            'quiet_hours.to' => 'required_if:quiet_hours.enabled,true|date_format:H:i',
            
            'active_days' => 'sometimes|array',
            'active_days.*' => 'integer|min:0|max:6',
            
            'enable_digest' => 'sometimes|boolean',
            'digest_frequency' => [
                'required_if:enable_digest,true',
                'nullable',
                Rule::in(['daily', 'weekly'])
            ],
            'digest_time' => 'required_if:enable_digest,true|nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'email_enabled.boolean' => 'El campo notificaciones por email debe ser verdadero o falso',
            'whatsapp_enabled.boolean' => 'El campo notificaciones por WhatsApp debe ser verdadero o falso',
            'in_app_enabled.boolean' => 'El campo notificaciones en la app debe ser verdadero o falso',
            
            'type_preferences.array' => 'Las preferencias por tipo deben ser un objeto',
            
            'quiet_hours.enabled.boolean' => 'El campo horas silenciosas debe ser verdadero o falso',
            'quiet_hours.from.required_if' => 'La hora de inicio es requerida cuando las horas silenciosas están activadas',
            'quiet_hours.from.date_format' => 'La hora de inicio debe tener formato HH:MM (ej: 22:00)',
            'quiet_hours.to.required_if' => 'La hora de fin es requerida cuando las horas silenciosas están activadas',
            'quiet_hours.to.date_format' => 'La hora de fin debe tener formato HH:MM (ej: 08:00)',
            
            'active_days.array' => 'Los días activos deben ser un arreglo',
            'active_days.*.integer' => 'Cada día debe ser un número',
            'active_days.*.min' => 'Los días deben estar entre 0 (Domingo) y 6 (Sábado)',
            'active_days.*.max' => 'Los días deben estar entre 0 (Domingo) y 6 (Sábado)',
            
            'enable_digest.boolean' => 'El campo resumen diario debe ser verdadero o falso',
            'digest_frequency.required_if' => 'La frecuencia del resumen es requerida cuando el resumen está activado',
            'digest_frequency.in' => 'La frecuencia del resumen debe ser "daily" o "weekly"',
            'digest_time.required_if' => 'La hora del resumen es requerida cuando el resumen está activado',
            'digest_time.date_format' => 'La hora del resumen debe tener formato HH:MM (ej: 09:00)',
        ];
    }

    public function attributes(): array
    {
        return [
            'email_enabled' => 'notificaciones por email',
            'whatsapp_enabled' => 'notificaciones por WhatsApp',
            'in_app_enabled' => 'notificaciones en la app',
            'type_preferences' => 'preferencias por tipo',
            'quiet_hours' => 'horas silenciosas',
            'quiet_hours.enabled' => 'horas silenciosas activadas',
            'quiet_hours.from' => 'hora de inicio',
            'quiet_hours.to' => 'hora de fin',
            'active_days' => 'días activos',
            'enable_digest' => 'resumen activado',
            'digest_frequency' => 'frecuencia del resumen',
            'digest_time' => 'hora del resumen',
        ];
    }

    /**
     * Additional validation after basic rules pass
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that at least one channel is enabled
            if ($this->has('email_enabled') || $this->has('whatsapp_enabled') || $this->has('in_app_enabled')) {
                $emailEnabled = $this->input('email_enabled', true);
                $whatsappEnabled = $this->input('whatsapp_enabled', false);
                $inAppEnabled = $this->input('in_app_enabled', true);

                if (!$emailEnabled && !$whatsappEnabled && !$inAppEnabled) {
                    $validator->errors()->add('channels', 'Debes tener al menos un canal de notificación activado');
                }
            }

            // Validate active_days are unique
            if ($this->has('active_days')) {
                $days = $this->input('active_days');
                if (count($days) !== count(array_unique($days))) {
                    $validator->errors()->add('active_days', 'Los días activos no pueden repetirse');
                }
            }
        });
    }
}