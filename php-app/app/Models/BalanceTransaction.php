<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount_units',
        'required_amount_units',
        'balance_after_units',
        'billing_date',
        'description',
        'payment_order_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount_units' => 'integer',
            'required_amount_units' => 'integer',
            'balance_after_units' => 'integer',
            'billing_date' => 'date',
            'payment_order_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_order_id');
    }
}
