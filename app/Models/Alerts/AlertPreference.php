<?php

namespace App\Models\Alerts;

use App\Models\Personal\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'email_enabled',
        'whatsapp_enabled',
        'in_app_enabled',
        'type_preferences',
        'quiet_hours',
        'active_days',
        'enable_digest',
        'digest_frequency',
        'digest_time',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'type_preferences' => 'array',
        'quiet_hours' => 'array',
        'active_days' => 'array',
        'enable_digest' => 'boolean',
        'digest_time' => 'datetime',
    ];

    // ==================== Relationships ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Helper Methods ====================

    public function isChannelEnabled(string $channel): bool
    {
        return match($channel) {
            'email' => $this->email_enabled,
            'whatsapp' => $this->whatsapp_enabled,
            'in_app' => $this->in_app_enabled,
            default => false,
        };
    }

    public function isTypeChannelEnabled(string $alertType, string $channel): bool
    {
        // Check global channel setting first
        if (!$this->isChannelEnabled($channel)) {
            return false;
        }

        // Check type-specific preferences
        if (!$this->type_preferences) {
            return true; // Default to enabled if no specific preferences
        }

        $typePrefs = data_get($this->type_preferences, $alertType);
        
        if (!$typePrefs) {
            return true; // No specific preference, use global
        }

        return data_get($typePrefs, $channel, true);
    }

    public function isInQuietHours(Carbon $time = null): bool
    {
        if (!$this->quiet_hours || !data_get($this->quiet_hours, 'enabled')) {
            return false;
        }

        $time = $time ?? now($this->user->timezone ?? 'America/Argentina/Buenos_Aires');
        
        $from = data_get($this->quiet_hours, 'from');
        $to = data_get($this->quiet_hours, 'to');

        if (!$from || !$to) {
            return false;
        }

        $fromTime = Carbon::createFromTimeString($from, $time->timezone);
        $toTime = Carbon::createFromTimeString($to, $time->timezone);

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($toTime->lessThan($fromTime)) {
            return $time->greaterThanOrEqualTo($fromTime) || $time->lessThan($toTime);
        }

        return $time->between($fromTime, $toTime);
    }

    public function isActiveDayOfWeek(int $dayOfWeek = null): bool
    {
        if (!$this->active_days) {
            return true; // All days active if not specified
        }

        $dayOfWeek = $dayOfWeek ?? now()->dayOfWeek;
        
        return in_array($dayOfWeek, $this->active_days);
    }

    public function canSendNotification(string $alertType, string $channel, Carbon $time = null): bool
    {
        // Check if channel is enabled for this alert type
        if (!$this->isTypeChannelEnabled($alertType, $channel)) {
            return false;
        }

        // Check quiet hours
        if ($this->isInQuietHours($time)) {
            return false;
        }

        // Check active days (skip for critical alerts)
        $criticalTypes = ['budget_exceeded', 'low_balance'];
        if (!in_array($alertType, $criticalTypes) && !$this->isActiveDayOfWeek()) {
            return false;
        }

        return true;
    }

    public function getPreferredChannels(string $alertType): array
    {
        $channels = [];

        if ($this->isTypeChannelEnabled($alertType, 'email')) {
            $channels[] = 'email';
        }

        if ($this->isTypeChannelEnabled($alertType, 'whatsapp')) {
            $channels[] = 'whatsapp';
        }

        if ($this->isTypeChannelEnabled($alertType, 'in_app')) {
            $channels[] = 'in_app';
        }

        return $channels;
    }

    public function shouldUseDigest(): bool
    {
        return $this->enable_digest && $this->digest_frequency && $this->digest_time;
    }

    public function updateTypePreference(string $alertType, string $channel, bool $enabled): bool
    {
        $prefs = $this->type_preferences ?? [];
        
        if (!isset($prefs[$alertType])) {
            $prefs[$alertType] = [];
        }

        $prefs[$alertType][$channel] = $enabled;
        
        $this->type_preferences = $prefs;
        return $this->save();
    }

    public function setQuietHours(string $from, string $to, bool $enabled = true): bool
    {
        $this->quiet_hours = [
            'enabled' => $enabled,
            'from' => $from,
            'to' => $to,
            'timezone' => $this->user->timezone ?? 'America/Argentina/Buenos_Aires',
        ];

        return $this->save();
    }

    public function setActiveDays(array $days): bool
    {
        // Validate days are between 0-6 (Sunday-Saturday)
        $validDays = array_filter($days, fn($day) => $day >= 0 && $day <= 6);
        
        $this->active_days = $validDays;
        return $this->save();
    }

    // ==================== Static Methods ====================

    public static function createDefault(User $user): self
    {
        return self::create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'in_app_enabled' => true,
            'enable_digest' => false,
        ]);
    }
}