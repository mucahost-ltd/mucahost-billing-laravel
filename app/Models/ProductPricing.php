<?php

namespace App\Models;

use Database\Factories\ProductPricingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricing extends Model
{
    /** @use HasFactory<ProductPricingFactory> */
    use HasFactory;

    protected $table = 'product_pricing';

    protected $fillable = ['product_id', 'currency_id', 'billing_cycle', 'price', 'setup_fee'];

    protected $casts = [
        'price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
