<?php

use App\Jobs\ProvisionService;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ProductPricing;
use App\Models\Service;
use App\Services\CheckoutService;
use App\Services\Enhance\EnhanceProvisioningService;
use App\Services\OrderProvisioningService;
use GoSuccess\Enhance\Client\Configuration;
use GoSuccess\Enhance\Contract\HttpClientInterface;
use GoSuccess\Enhance\DTO\NewCustomer;
use GoSuccess\Enhance\DTO\NewSubscription;
use GoSuccess\Enhance\DTO\NewWebsite;
use GoSuccess\Enhance\DTO\UpdateWebsite;
use GoSuccess\Enhance\Enhance;
use GoSuccess\Enhance\Enum\HttpMethod;
use GoSuccess\Enhance\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function paidHostingService(): Service
{
    Queue::fake([ProvisionService::class]);
    $client = Client::factory()->create();
    $price = ProductPricing::factory()->create();
    $invoice = app(CheckoutService::class)->checkout($client, $price->id, 'example.com', (string) Str::uuid(), null);
    $invoice->update(['status' => 'paid']);
    app(OrderProvisioningService::class)->approve($invoice->order);
    Queue::assertPushed(ProvisionService::class, 1);

    return Service::sole();
}

function enhanceTransport(): MockInterface
{
    $transport = Mockery::mock(HttpClientInterface::class);
    app()->instance(Enhance::class, new Enhance(new Configuration('https://panel.example.test/api', 'parent-org', 'test-token'), $transport));

    return $transport;
}

test('SDK creates one customer subscription and website across repeated jobs', function () {
    $this->freezeTime();
    $service = paidHostingService();
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/parent-org/customers', [], Mockery::type(NewCustomer::class))->andReturn(new Response(201, ['id' => 'customer-org'], ''));
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/parent-org/customers/customer-org/subscriptions', [], Mockery::on(fn ($body) => $body instanceof NewSubscription && $body->planId === 7))->andReturn(new Response(201, ['id' => 42], ''));
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/customer-org/websites', ['kind' => null], Mockery::on(fn ($body) => $body instanceof NewWebsite && $body->subscriptionId === 42 && $body->domain === 'example.com'))->andReturn(new Response(201, ['id' => 'website-id'], ''));
    $job = new ProvisionService($service);
    $job->handle(app(EnhanceProvisioningService::class));
    $job->handle(app(EnhanceProvisioningService::class));
    expect($service->fresh()->status)->toBe('active');
    expect($service->fresh()->enhance_account_id)->toBe(['org_id' => 'customer-org', 'subscription_id' => 42, 'website_id' => 'website-id']);
    expect($service->fresh()->next_due_date)->not->toBeNull();
});

test('uncertain website creation preserves IDs and prevents automatic duplicate creation', function () {
    $service = paidHostingService();
    $service->client->update(['enhance_org_id' => 'customer-org']);
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/parent-org/customers/customer-org/subscriptions', [], Mockery::type(NewSubscription::class))->andReturn(new Response(201, ['id' => 42], ''));
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/customer-org/websites', ['kind' => null], Mockery::type(NewWebsite::class))->andThrow(new RuntimeException('Timeout after remote create'));
    $provisioning = app(EnhanceProvisioningService::class);
    $provisioning->provision($service);
    $provisioning->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('needs_review');
    expect($service->fresh()->status)->toBe('pending');
    expect($service->fresh()->enhance_account_id['subscription_id'])->toBe(42);
});

test('unknown customer creation is never replayed', function () {
    $service = paidHostingService();
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/parent-org/customers', [], Mockery::type(NewCustomer::class))->andThrow(new RuntimeException('Connection lost'));
    app(EnhanceProvisioningService::class)->provision($service);
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('needs_review');
    expect((bool) $service->client->fresh()->enhance_org_pending)->toBeTrue();
});

test('saved subscription and customer are reused when provisioning resumes', function () {
    $service = paidHostingService();
    $service->client->update(['enhance_org_id' => 'customer-org']);
    $service->update(['enhance_account_id' => ['org_id' => 'customer-org', 'subscription_id' => 42]]);
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/customer-org/websites', ['kind' => null], Mockery::type(NewWebsite::class))->andReturn(new Response(201, ['id' => 'website-id'], ''));
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->status)->toBe('active');
});

test('missing plan can be corrected and retried without sending a remote request', function () {
    $service = paidHostingService();
    $service->product->update(['module_settings' => ['plan' => 'old-slug']]);
    $transport = enhanceTransport();
    $transport->shouldNotReceive('request');
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('failed');
    expect($service->fresh()->provisioning_step)->toBeNull();
});

test('duplicate workers cannot enter an already processing service', function () {
    $service = paidHostingService();
    $service->update(['provisioning_status' => 'processing', 'provisioning_step' => 'subscription']);
    $transport = enhanceTransport();
    $transport->shouldNotReceive('request');
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->status)->toBe('pending');
});

test('worker failure leaves processing services for review', function () {
    $service = paidHostingService();
    $service->update(['provisioning_status' => 'processing']);
    (new ProvisionService($service))->failed(new RuntimeException('Worker timeout'));
    expect($service->fresh()->provisioning_status)->toBe('needs_review');
});

test('missing customer IDs are treated as an uncertain remote result', function () {
    $service = paidHostingService();
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::POST, '/orgs/parent-org/customers', [], Mockery::type(NewCustomer::class))->andReturn(new Response(201, [], ''));
    app(EnhanceProvisioningService::class)->provision($service);
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('needs_review');
});

test('provisioning cannot run when payment has been cancelled', function () {
    $service = paidHostingService();
    Invoice::query()->update(['status' => 'cancelled']);
    $transport = enhanceTransport();
    $transport->shouldNotReceive('request');
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('failed');
});

test('durable remote intent prevents a create even after a status reset', function () {
    $service = paidHostingService();
    $service->update(['provisioning_step' => 'subscription', 'provisioning_status' => 'failed']);
    $transport = enhanceTransport();
    $transport->shouldNotReceive('request');
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('needs_review');
});

test('another service cannot create a customer while its creation is pending', function () {
    $service = paidHostingService();
    $service->client->update(['enhance_org_pending' => true]);
    $transport = enhanceTransport();
    $transport->shouldNotReceive('request');
    app(EnhanceProvisioningService::class)->provision($service);
    expect($service->fresh()->provisioning_status)->toBe('failed');
});

test('existing suspend and reactivate operations use the installed SDK', function () {
    $service = paidHostingService();
    $service->update(['status' => 'active', 'enhance_account_id' => ['org_id' => 'customer-org', 'website_id' => 'website-id', 'subscription_id' => 42]]);
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::PATCH, '/orgs/customer-org/websites/website-id', [], Mockery::on(fn ($body) => $body instanceof UpdateWebsite && $body->isSuspended === true))->andReturn(new Response(200, null, ''));
    $transport->shouldReceive('request')->once()->with(HttpMethod::PATCH, '/orgs/customer-org/websites/website-id', [], Mockery::on(fn ($body) => $body instanceof UpdateWebsite && $body->isSuspended === false))->andReturn(new Response(200, null, ''));
    $provisioning = app(EnhanceProvisioningService::class);
    $provisioning->suspend($service);
    expect($service->fresh()->status)->toBe('suspended');
    $provisioning->reactivate($service);
    expect($service->fresh()->status)->toBe('active');
});

test('termination uses scoped SDK calls and does not replay after completion', function () {
    $service = paidHostingService();
    $service->update(['status' => 'active', 'enhance_account_id' => ['org_id' => 'customer-org', 'website_id' => 'website-id', 'subscription_id' => 42]]);
    $transport = enhanceTransport();
    $transport->shouldReceive('request')->once()->with(HttpMethod::DELETE, '/orgs/customer-org/websites/website-id', ['force' => null])->andReturn(new Response(200, null, ''));
    $transport->shouldReceive('request')->once()->with(HttpMethod::DELETE, '/orgs/customer-org/subscriptions/42', ['force' => null])->andReturn(new Response(200, null, ''));
    app(EnhanceProvisioningService::class)->terminate($service);
    app(EnhanceProvisioningService::class)->terminate($service);
    expect($service->fresh()->status)->toBe('terminated');
});
