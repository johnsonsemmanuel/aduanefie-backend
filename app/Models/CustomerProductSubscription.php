<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProductSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_id',
        'phone',
        'product_id',
        'product_type',
        'subscribed',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->morphTo(__CLASS__, 'product', 'product_id', 'product_type');
    }

    public function scopeActive($query)
    {
        return $query->where('subscribed', true)
            ->where(function ($q) {
                $q->whereNull('unsubscribed_at')
                    ->orWhere('unsubscribed_at', '>', now());
            });
    }

    public function scopeForProduct($query, $productId, $productType = 'App\Models\Item')
    {
        return $query->where('product_id', $productId)
            ->where('product_type', $productType)
            ->active();
    }
}
