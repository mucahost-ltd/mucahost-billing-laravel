<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPricing;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private readonly OrderProvisioningService $provisioning) {}

    public function checkout(Client $client, int $pricingId, ?string $domain, string $token, ?string $ip): Invoice|Order
    {
        return DB::transaction(function () use ($client, $pricingId, $domain, $token, $ip) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            $existing = Order::query()->where('checkout_token', $token)->first();
            if ($existing) {
                abort_unless($existing->client_id === $client->id, 404);

                return Invoice::query()->where('order_id', $existing->id)->first() ?? $existing;
            }
            $pricing = ProductPricing::query()->with('product')->findOrFail($pricingId);
            $product = Product::query()->lockForUpdate()->with('group')->findOrFail($pricing->product_id);
            $pricing = $product->pricing()->findOrFail($pricingId);
            if (! $product->is_active || ! $product->is_visible || ! $product->group->is_visible || ! in_array($product->module, ['enhance', 'manual'], true) || $product->type === 'domain') {
                throw ValidationException::withMessages(['pricing_id' => 'This hosting package is unavailable.']);
            }
            if (! in_array($pricing->billing_cycle, ($product->billing_type === 'recurring' ? ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] : ['one_time']), true) || BigDecimal::of($pricing->price)->isNegative() || BigDecimal::of($pricing->setup_fee)->isNegative()) {
                throw ValidationException::withMessages(['pricing_id' => 'This package has invalid pricing.']);
            }
            if ($product->requires_domain && ! $domain) {
                throw ValidationException::withMessages(['domain' => 'A domain is required for this product.']);
            }
            $total = (string) BigDecimal::of($pricing->price)->plus($pricing->setup_fee)->toScale(2);
            $order = Order::create([
                'order_number' => 'ORD-'.Str::ulid(), 'client_id' => $client->id,
                'status' => 'pending', 'total' => $total, 'currency_id' => $pricing->currency_id,
                'ip_address' => $ip, 'checkout_token' => $token,
            ]);
            $order->items()->create([
                'product_id' => $product->id, 'domain' => $domain,
                'billing_cycle' => $product->billing_type === 'free' ? 'free' : $pricing->billing_cycle, 'price' => $product->billing_type === 'free' ? '0.00' : $pricing->price,
                'provisioning_settings' => ['module' => $product->module, 'module_settings' => $product->module_settings, 'setup_mode' => $product->setup_mode, 'billing_type' => $product->billing_type],
            ]);
            if ($product->billing_type === 'free') {
                $order->update(['total' => '0.00']);
                $this->provisioning->placed($order);
                $this->provisioning->approve($order);

                return $order;
            }
            $invoice = Invoice::create([
                'invoice_number' => 'INV-'.Str::ulid(), 'order_id' => $order->id,
                'client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => $total,
                'tax' => '0.00', 'total' => $total, 'currency_id' => $pricing->currency_id,
                'due_date' => now()->addDays(7),
            ]);
            $invoice->items()->create(['description' => $product->name.' — '.$domain.' ('.$pricing->billing_cycle.')', 'amount' => $pricing->price]);
            if (BigDecimal::of($pricing->setup_fee)->isPositive()) {
                $invoice->items()->create(['description' => 'One-time setup fee', 'amount' => $pricing->setup_fee]);
            }

            $this->provisioning->placed($order);
            if (BigDecimal::of($total)->isZero()) {
                $invoice->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => 'zero_balance']);
                $this->provisioning->approve($order);
            }

            return $invoice;
        });
    }
}
