<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vk_conversation_id', 'direction', 'body', 'vk_message_id', 'payload'])]
class VkMessage extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(VkConversation::class, 'vk_conversation_id');
    }
}
