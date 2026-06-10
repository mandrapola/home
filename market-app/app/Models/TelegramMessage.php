<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['telegram_conversation_id', 'direction', 'body', 'telegram_message_id', 'admin_message_id', 'payload'])]
class TelegramMessage extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TelegramConversation::class, 'telegram_conversation_id');
    }
}
