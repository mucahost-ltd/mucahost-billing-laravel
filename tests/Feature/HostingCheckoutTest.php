<?php

use App\Jobs\ProvisionService;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ProductPricing;
use App\Models\Service;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderProvisioningService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function hostingInvoice(): Invoice
{
    $client = Client::factory()->create();
    $price = ProductPricing::factory()->create();

    return app(CheckoutService::class)->checkout($client, $price->id, 'example.com', (string) Str::uuid(), '127.0.0.1');
}

test('client pages resolve and guests cannot order', function () {
    $this->get(route('client.login'))->assertInertia(fn (Assert $page) => $page->component('client/auth/login'));
    $this->get(route('client.register'))->assertInertia(fn (Assert $page) => $page->component('client/auth/register'));
    $this->get(route('client.orders.create'))->assertRedirect(route('client.login'));
    $this->post(route('client.orders.store'), [])->assertRedirect(route('client.login'));
});

test('active clients can browse packages and their dashboard', function () {
    $client = Client::factory()->create();
    ProductPricing::factory()->create();
    $this->actingAs($client, 'client')->get(route('client.orders.create'))
        ->assertInertia(fn (Assert $page) => $page->component('client/order')->has('products', 1)->missing('products.0.module_settings'));
    $this->get(route('client.dashboard'))->assertInertia(fn (Assert $page) => $page->component('client/dashboard'));
});

test('checkout uses stored pricing and deduplicates a repeated submission', function () {
    Queue::fake([ProvisionService::class]);
    $client = Client::factory()->create();
    $price = ProductPricing::factory()->create();
    $payload = ['pricing_id' => $price->id, 'domain' => ' Example.COM ', 'checkout_token' => (string) Str::uuid(), 'total' => '0.01', 'client_id' => 999];
    $this->actingAs($client, 'client')->post(route('client.orders.store'), $payload)->assertRedirect();
    $invoice = Invoice::sole();
    $this->post(route('client.orders.store'), $payload)->assertRedirect(route('client.invoices.show', $invoice));
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('services', 0);
    $this->assertDatabaseHas('invoices', ['client_id' => $client->id, 'total' => '12.30', 'status' => 'unpaid']);
    $this->assertDatabaseHas('order_items', ['domain' => 'example.com', 'price' => '10.10']);
    $this->assertDatabaseHas('invoice_items', ['description' => 'One-time setup fee', 'amount' => '2.20']);
    Queue::assertNothingPushed();
    $this->get(route('client.invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->component('client/invoice')->where('invoice.total', '12.30'));
});

test('checkout rejects invalid domain and missing fields', function () {
    $client = Client::factory()->create();
    $price = ProductPricing::factory()->create();
    $this->actingAs($client, 'client')->post(route('client.orders.store'), [])->assertSessionHasErrors(['pricing_id', 'domain', 'checkout_token']);
    $this->post(route('client.orders.store'), ['pricing_id' => $price->id, 'domain' => 'https://example.com/path', 'checkout_token' => (string) Str::uuid()])->assertSessionHasErrors('domain');
    $this->assertDatabaseCount('orders', 0);
});

test('unavailable packages cannot be ordered', function (array $attributes) {
    $client = Client::factory()->create();
    $price = ProductPricing::factory()->create();
    $price->product->update($attributes);
    $this->actingAs($client, 'client')->post(route('client.orders.store'), ['pricing_id' => $price->id, 'domain' => 'example.com', 'checkout_token' => (string) Str::uuid()])->assertSessionHasErrors('pricing_id');
    $this->assertDatabaseCount('orders', 0);
})->with([[['is_active' => false]], [['module' => 'other']], [['type' => 'domain']]]);

test('closed clients cannot access billing or order', function () {
    $client = Client::factory()->create(['status' => 'closed']);
    $this->actingAs($client, 'client')->get(route('client.dashboard'))->assertForbidden();
    $this->post(route('client.orders.store'), [])->assertForbidden();
});

test('clients cannot see another invoice or reuse another checkout token', function () {
    $invoice = hostingInvoice();
    $other = Client::factory()->create();
    $this->actingAs($other, 'client')->get(route('client.invoices.show', $invoice))->assertNotFound();
    $this->post(route('client.orders.store'), ['pricing_id' => ProductPricing::sole()->id, 'domain' => 'other.com', 'checkout_token' => Order::sole()->checkout_token])->assertNotFound();
});

test('only admins can confirm payment or view billing', function (string $role) {
    Queue::fake([ProvisionService::class]);
    $invoice = hostingInvoice();
    $staff = User::factory()->create(['role' => $role]);
    $this->actingAs($staff, 'web')->get(route('billing.index'))->assertForbidden();
    $this->post(route('billing.confirm', $invoice), ['reference' => 'BANK-1', 'received' => true])->assertForbidden();
    expect($invoice->fresh()->status)->toBe('unpaid');
    Queue::assertNothingPushed();
})->with(['support', 'sales']);

test('client login alone cannot grant staff billing access', function () {
    $invoice = hostingInvoice();
    $this->actingAs($invoice->client, 'client')->post(route('billing.confirm', $invoice), ['reference' => 'BANK-1', 'received' => true])->assertRedirect(route('login'));
});

test('admin payment confirmation records exactly one payment and hosting service', function () {
    Queue::fake([ProvisionService::class]);
    $invoice = hostingInvoice();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'web')->get(route('billing.index'))->assertInertia(fn (Assert $page) => $page->component('billing')->has('recentInvoices', 1));
    $this->post(route('billing.confirm', $invoice), ['reference' => 'BANK-1', 'received' => true])->assertRedirect();
    $this->post(route('billing.confirm', $invoice), ['reference' => 'BANK-1', 'received' => true])->assertRedirect();
    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('services', 1);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'confirmed_by' => $admin->id]);
    $this->assertDatabaseHas('transactions', ['invoice_id' => $invoice->id, 'amount' => '12.30', 'gateway' => 'manual']);
    $this->assertDatabaseHas('services', ['order_id' => $invoice->order_id, 'status' => 'pending', 'amount' => '10.10']);
    Queue::assertPushed(ProvisionService::class, 1);
});

test('confirmation needs receipt verification and reference', function () {
    $invoice = hostingInvoice();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'web')->post(route('billing.confirm', $invoice), [])->assertSessionHasErrors(['reference', 'received']);
    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('cancelled invoice cannot be paid or provisioned', function () {
    Queue::fake([ProvisionService::class]);
    $invoice = hostingInvoice();
    $invoice->update(['status' => 'cancelled']);
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')->post(route('billing.confirm', $invoice), ['reference' => 'BANK-1', 'received' => true])->assertSessionHasErrors('invoice');
    $this->assertDatabaseCount('transactions', 0);
    Queue::assertNothingPushed();
});

test('unpaid orders cannot bypass payment through direct approval', function () {
    $invoice = hostingInvoice();
    expect(fn () => app(OrderProvisioningService::class)->approve($invoice->order))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('services', 0);
});

test('retry is forbidden for an uncertain remote request', function () {
    Queue::fake([ProvisionService::class]);
    $invoice = hostingInvoice();
    $invoice->update(['status' => 'paid']);
    app(OrderProvisioningService::class)->approve($invoice->order);
    $service = Service::sole();
    $service->update(['provisioning_status' => 'needs_review']);
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')->post(route('billing.retry', $service))->assertConflict();
    Queue::assertPushed(ProvisionService::class, 1);
});

test('admin can map an Enhance plan without discarding product settings', function () {
    $price = ProductPricing::factory()->create();
    $price->product->update(['module_settings' => ['disk_gb' => 5]]);
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
        ->patch(route('billing.plans.update', $price->product), ['plan_id' => 42])->assertRedirect();
    expect($price->product->fresh()->module_settings)->toBe(['disk_gb' => 5, 'plan_id' => 42]);
    $this->patch(route('billing.plans.update', $price->product), ['plan_id' => 'shared-starter'])->assertSessionHasErrors('plan_id');
});

test('support cannot change the Enhance plan mapping', function () {
    $price = ProductPricing::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'support']), 'web')
        ->patch(route('billing.plans.update', $price->product), ['plan_id' => 42])->assertForbidden();
});

test('the same manual receipt cannot pay two invoices', function () {
    Queue::fake([ProvisionService::class]);
    $first = hostingInvoice();
    $second = hostingInvoice();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
        ->post(route('billing.confirm', $first), ['reference' => 'BANK-1', 'received' => true])->assertRedirect();
    $this->post(route('billing.confirm', $second), ['reference' => 'BANK-1', 'received' => true])->assertSessionHasErrors('reference');
    expect($second->fresh()->status)->toBe('unpaid');
    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('services', 1);
    Queue::assertPushed(ProvisionService::class, 1);
});

test('admin can requeue configuration failures but support cannot', function () {
    Queue::fake([ProvisionService::class]);
    $invoice = hostingInvoice();
    $invoice->update(['status' => 'paid']);
    app(OrderProvisioningService::class)->approve($invoice->order);
    $service = Service::sole();
    $service->update(['provisioning_status' => 'failed']);
    $this->actingAs(User::factory()->create(['role' => 'support']), 'web')->post(route('billing.retry', $service))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')->post(route('billing.retry', $service))->assertRedirect();
    Queue::assertPushed(ProvisionService::class, 2);
});

test('client registration creates a client without staff privileges', function () {
    $this->post(route('client.register.store'), [
        'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.com',
        'password' => 'password12345', 'password_confirmation' => 'password12345', 'role' => 'admin',
    ])->assertRedirect(route('client.dashboard'));
    $this->assertAuthenticated('client');
    $this->assertGuest('web');
    $this->assertDatabaseCount('users', 0);
});
