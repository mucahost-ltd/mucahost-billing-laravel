<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreOrderRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPricing;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('client/order', [
            'products' => Product::query()->where('is_active', true)->where('is_visible', true)->whereHas('group', fn ($query) => $query->where('is_visible', true))->whereIn('module', ['enhance', 'manual'])->where('type', '!=', 'domain')
                ->with(['pricing.currency', 'group'])->orderBy('sort_order')->orderBy('name')->get()->map(fn (Product $product): array => [
                    'group' => ['id' => $product->group->id, 'name' => $product->group->name, 'sort_order' => $product->group->sort_order],
                    'billing_type' => $product->billing_type, 'requires_domain' => $product->requires_domain,
                    'id' => $product->id, 'name' => $product->name, 'description' => $product->description,
                    'pricing' => $product->pricing->map(fn (ProductPricing $price): array => [
                        'id' => $price->id, 'billing_cycle' => $price->billing_cycle,
                        'price' => $price->price, 'setup_fee' => $price->setup_fee, 'currency' => $price->currency->code,
                    ]),
                ]),
            'checkoutToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreOrderRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $invoice = $checkout->checkout($request->user('client'), $request->integer('pricing_id'), $request->validated('domain'), $request->validated('checkout_token'), $request->ip());

        return $invoice instanceof Invoice ? redirect()->route('client.invoices.show', $invoice) : redirect()->route('client.orders.show', $invoice);
    }

    public function confirmation(Request $request, Order $order): Response
    {
        abort_unless($order->client_id === $request->user('client')->id, 404);

        return Inertia::render('client/order-complete', ['order' => $order->only(['id', 'order_number', 'status'])]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->client_id === $request->user('client')->id, 404);

        return Inertia::render('client/invoice', [
            'invoice' => $invoice->only(['id', 'invoice_number', 'status', 'subtotal', 'tax', 'total', 'due_date', 'paid_at']),
            'currency' => $invoice->currency->code,
            'items' => $invoice->items()->get(['id', 'description', 'amount']),
        ]);
    }
}
