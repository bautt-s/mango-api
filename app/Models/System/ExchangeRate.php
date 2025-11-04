<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_code',
        'quote_code',
        'rate',
        'as_of_date',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'as_of_date' => 'date',
    ];

    // Scopes
    public function scopeForPair($query, string $baseCode, string $quoteCode)
    {
        return $query->where('base_code', $baseCode)
            ->where('quote_code', $quoteCode);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('as_of_date', 'desc');
    }

    public function scopeAsOfDate($query, $date)
    {
        return $query->where('as_of_date', '<=', $date)
            ->orderBy('as_of_date', 'desc');
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

        $rate = $query->first();

        if (!$rate) {
            return null;
        }

        return (int) round($amountCents * (float) $rate->rate);
    }

    public static function getRate(string $baseCode, string $quoteCode, $date = null): ?float
    {
        $query = static::forPair($baseCode, $quoteCode);

        if ($date) {
            $query->asOfDate($date);
        } else {
            $query->latest();
        }

        $rate = $query->first();

        return $rate ? (float) $rate->rate : null;
    }
}