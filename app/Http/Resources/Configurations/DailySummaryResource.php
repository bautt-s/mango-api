<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->summary_date,
            'transactions_count' => $this->transactions_count,
            
            // Amounts in cents
            'total_expense_cents' => $this->total_expense_cents,
            'total_income_cents' => $this->total_income_cents,
            'net_amount_cents' => $this->getNetAmountCents(),
            
            // Formatted amounts
            'total_expense' => $this->getTotalExpense(),
            'total_income' => $this->getTotalIncome(),
            'net_amount' => $this->getNetAmount(),
            
            // Formatted strings
            'formatted_expense' => $this->getFormattedExpense(),
            'formatted_income' => $this->getFormattedIncome(),
            'formatted_net' => $this->getFormattedNet(),
            
            'currency_code' => $this->currency_code,
            'channel' => $this->channel,
            'template_name' => $this->template_name,
            
            // Status
            'is_sent' => $this->isSent(),
            'sent_at' => $this->sent_at?->toISOString(),
            'has_activity' => $this->hasActivity(),
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}