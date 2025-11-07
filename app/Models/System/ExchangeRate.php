<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_date',
        'base_currency',
        'target_currency', 
        'rate_ppm',
    ];

    protected $casts = [
        'rate_ppm' => 'integer',
        'rate_date' => 'date',
    ];

    // Scopes
    public function scopeForPair($query, string $baseCurrency, string $targetCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
            ->where('target_currency', $targetCurrency);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('rate_date', 'desc');
    }

    public function scopeAsOfDate($query, $date)
    {
        return $query->where('rate_date', '<=', $date)
            ->orderBy('rate_date', 'desc');
    }

    // Accessor to convert rate_ppm to decimal
    public function getRateAttribute(): float
    {
        return $this->rate_ppm / 1000000;
    }

    // Mutator to store rate as ppm
    public function setRateAttribute($value)
    {
        $this->attributes['rate_ppm'] = (int) round($value * 1000000);
    }

    // Helper methods
    public static function convert(int $amountCents, string $fromCurrency, string $toCurrency, $date = null): ?int
    {
        if ($fromCurrency === $toCurrency) {
            return $amountCents;
        }

        $query = static::forPair($fromCurrency, $toCurrency);

        if ($date) {
            $query->asOfDate($date);
        } else {
            $query->latest();
        }

        $exchangeRate = $query->first();

        if (!$exchangeRate) {
            return null;
        }

        return (int) round($amountCents * $exchangeRate->rate);
    }

    public static function getRate(string $baseCurrency, string $targetCurrency, $date = null): ?float
    {
        $query = static::forPair($baseCurrency, $targetCurrency);

        if ($date) {
            $query->asOfDate($date);
        } else {
            $query->latest();
        }

        $exchangeRate = $query->first();

        return $exchangeRate ? $exchangeRate->rate : null;
    }
}
