<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'market_item_id',
    'order_id',
    'context_type',
    'context_token',
    'telegram_user_id',
    'telegram_username',
    'telegram_first_name',
    'intent',
    'status',
    'admin_message_id',
    'context_payload',
])]
class TelegramConversation extends Model
{
    protected function casts(): array
    {
        return [
            'context_payload' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'market_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }
}
