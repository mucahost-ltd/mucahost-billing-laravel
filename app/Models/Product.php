<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'product_group_id', 'name', 'slug', 'description',
        'type', 'module', 'module_settings', 'is_active', 'billing_type', 'setup_mode', 'is_visible', 'requires_domain', 'sort_order',
    ];

    protected $casts = [
        'module_settings' => 'array',
        'is_active' => 'boolean', 'is_visible' => 'boolean', 'requires_domain' => 'boolean',
    ];

    /** @return BelongsTo<ProductGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    /** @return HasMany<ProductPricing, $this> */
    public function pricing(): HasMany
    {
        return $this->hasMany(ProductPricing::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function priceFor(string $billingCycle, ?int $currencyId = null): ?ProductPricing
    {
        return $this->pricing()
            ->where('billing_cycle', $billingCycle)
            ->when($currencyId, fn ($q) => $q->where('currency_id', $currencyId))
            ->first();
    }
}
