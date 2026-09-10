<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'client_id', 'order_id', 'product_id', 'server_id', 'domain',
        'username', 'password', 'status', 'billing_cycle', 'amount',
        'next_due_date', 'enhance_account_id', 'order_item_id', 'provisioning_status', 'provisioning_step', 'provisioning_error', 'provisioning_settings', 'provisioning_authorized_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'provisioning_settings' => 'array', 'provisioning_authorized_at' => 'datetime',
        'password' => 'encrypted',
        'amount' => 'decimal:2',
        'next_due_date' => 'date',
        // Holds {org_id, subscription_id, website_id} from Enhance — see
        // EnhanceProvisioningService.
        'enhance_account_id' => 'array',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
