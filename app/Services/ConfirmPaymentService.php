<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPaymentService
{
    public function __construct(private readonly OrderProvisioningService $provisioning) {}

    public function confirm(Invoice $invoice, User $staff, string $reference): void
    {
        abort_unless($staff->role === 'admin', 403);
        try {
            DB::transaction(function () use ($invoice, $staff, $reference) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                if ($invoice->status === 'paid') {
                    return;
                }
                $order = Order::query()->lockForUpdate()->find($invoice->order_id);
                if (! in_array($invoice->status, ['unpaid', 'overdue'], true) || ! $order || $order->status !== 'pending' || $order->client_id !== $invoice->client_id || $order->currency_id !== $invoice->currency_id || $order->total !== $invoice->total) {
                    throw ValidationException::withMessages(['invoice' => 'This invoice cannot be confirmed.']);
                }
                $invoice->transactions()->create([
                    'client_id' => $invoice->client_id, 'gateway' => 'manual',
                    'gateway_transaction_id' => $reference, 'amount' => $invoice->total,
                    'currency_id' => $invoice->currency_id, 'type' => 'payment', 'status' => 'completed',
                ]);
                $invoice->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => 'manual', 'confirmed_by' => $staff->id, 'confirmation_reference' => trim($reference)]);
                $this->provisioning->approve($order);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['reference' => 'This payment reference has already been used.']);
        }
    }
}
