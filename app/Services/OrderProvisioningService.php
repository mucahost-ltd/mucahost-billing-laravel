<?php

namespace App\Services;

use App\Jobs\ProvisionService;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderProvisioningService
{
    public function __construct(private readonly ServerSelector $serverSelector) {}

    public function approve(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'active') {
                return;
            }
            $paid = Invoice::query()->where('order_id', $order->id)->where('status', 'paid')->exists();
            $free = $order->items()->get()->every(fn ($item): bool => ($item->provisioning_settings['billing_type'] ?? null) === 'free');
            if ($order->status !== 'pending' || (! $paid && ! $free)) {
                throw ValidationException::withMessages(['invoice' => 'Only a paid, pending order can be activated.']);
            }
            $this->prepare($order, 'payment');
            $order->update(['status' => 'active']);
        });
    }

    public function placed(Order $order): void
    {
        DB::transaction(fn () => $this->prepare($order, 'order'));
    }

    public function accept(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, ['cancelled', 'fraud'], true)) {
                throw ValidationException::withMessages(['order' => 'This order cannot be accepted.']);
            }
            $order->update(['accepted_at' => now()]);
            $this->prepare($order, 'accept');
        });
    }

    private function prepare(Order $order, string $trigger): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $settings = $item->provisioning_settings ?? [
                'module' => $item->product->module, 'module_settings' => $item->product->module_settings,
                'setup_mode' => $item->product->setup_mode, 'billing_type' => $item->product->billing_type,
            ];
            $mode = $settings['setup_mode'] ?? 'after_payment';
            if ($trigger === 'order' && $mode !== 'on_order') {
                continue;
            }
            $service = Service::query()->firstOrCreate(['order_item_id' => $item->id], [
                'client_id' => $order->client_id, 'order_id' => $order->id, 'product_id' => $item->product_id,
                'server_id' => $this->serverSelector->pickFor($item->product)?->id,
                'domain' => $item->domain, 'status' => 'pending', 'billing_cycle' => $item->billing_cycle,
                'amount' => $item->price, 'provisioning_status' => 'awaiting_approval', 'provisioning_settings' => $settings,
            ]);
            if ($mode === 'on_order' || ($mode === 'after_payment' && $trigger === 'payment') || ($mode === 'on_accept' && $trigger === 'accept')) {
                $this->activate($service);
            }
        }
    }

    public function activate(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($service->status !== 'pending' || $service->provisioning_status !== 'awaiting_approval') {
                return;
            }
            $service->update(['provisioning_authorized_at' => now(), 'provisioning_status' => 'queued']);
            $module = $service->provisioning_settings['module'] ?? $service->product->module;
            if ($module === 'manual') {
                $months = ['monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 36];
                $service->update(['status' => 'active', 'provisioning_status' => 'completed', 'next_due_date' => isset($months[$service->billing_cycle]) ? now()->addMonthsNoOverflow($months[$service->billing_cycle]) : null]);
            } else {
                ProvisionService::dispatch($service)->afterCommit();
            }
        });
    }
}
