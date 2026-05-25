<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'inventory_count',
        'is_flash_sale',
        'flash_sale_price',
        'flash_sale_starts_at',
        'flash_sale_ends_at',
    ];

    protected $casts = [
        'is_flash_sale'        => 'boolean',
        'flash_sale_starts_at' => 'datetime',
        'flash_sale_ends_at'   => 'datetime',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Returns true if this product currently has an active flash sale.
     */
    public function isFlashSaleActive(): bool
    {
        if (! $this->is_flash_sale || ! $this->flash_sale_price) {
            return false;
        }

        $now = now();

        if ($this->flash_sale_starts_at && $now->lt($this->flash_sale_starts_at)) {
            return false;
        }

        if ($this->flash_sale_ends_at && $now->gt($this->flash_sale_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the effective price to charge, flash sale price if active, otherwise regular price.
     */
    public function getEffectivePrice(): int
    {
        return $this->isFlashSaleActive()
            ? $this->flash_sale_price
            : $this->price;
    }
}