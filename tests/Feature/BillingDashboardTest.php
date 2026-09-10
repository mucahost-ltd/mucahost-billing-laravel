<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin billing overview returns invoice metrics without product management data', function () {
    $currency = Currency::factory()->create(['code' => 'BDT', 'symbol' => '৳']);
    $client = Client::factory()->create(['first_name' => 'Mahmud', 'last_name' => 'Sabuj']);
    Invoice::factory()->create(['invoice_number' => 'INV-1001', 'client_id' => $client->id, 'currency_id' => $currency->id, 'status' => 'paid', 'subtotal' => 1500, 'total' => 1500, 'paid_at' => now()]);
    Invoice::factory()->create(['client_id' => $client->id, 'currency_id' => $currency->id, 'status' => 'unpaid', 'due_date' => now()->subDay()]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing')->where('view', 'overview')->where('stats.all', 2)
            ->where('stats.paid', 1)->where('stats.overdue', 1)
            ->has('recentInvoices', 2)->has('moneyTotals', 1)
            ->missing('products')->missing('orders')->missing('services'));
});

test('admin can search and filter all invoices', function () {
    $currency = Currency::factory()->create();
    $matchingClient = Client::factory()->create(['first_name' => 'Aloiu', 'last_name' => 'Pousa']);
    $otherClient = Client::factory()->create();
    Invoice::factory()->create(['invoice_number' => 'INV-OVERDUE', 'client_id' => $matchingClient->id, 'currency_id' => $currency->id, 'status' => 'unpaid', 'due_date' => now()->subDay()]);
    Invoice::factory()->create(['invoice_number' => 'INV-FUTURE', 'client_id' => $otherClient->id, 'currency_id' => $currency->id, 'status' => 'unpaid', 'due_date' => now()->addDay()]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('billing.invoices.index', ['search' => 'Aloiu', 'status' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing')->where('view', 'invoices')->has('invoices.data', 1)
            ->where('invoices.data.0.invoice_number', 'INV-OVERDUE')
            ->where('invoices.data.0.status', 'overdue'));
});

test('non admin staff cannot view either billing screen', function (string $routeName) {
    $supportUser = User::factory()->create(['role' => 'support']);

    $this->actingAs($supportUser, 'web')->get(route($routeName))->assertForbidden();
})->with(['billing.index', 'billing.invoices.index']);

test('invoice filters reject unsupported values', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('billing.invoices.index', ['status' => 'pending', 'sort' => 'client']))
        ->assertSessionHasErrors(['status', 'sort']);
});
