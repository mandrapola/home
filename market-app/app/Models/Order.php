<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'customer_name', 'customer_email', 'customer_phone', 'comment', 'status', 'total_rub'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'total_rub' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
