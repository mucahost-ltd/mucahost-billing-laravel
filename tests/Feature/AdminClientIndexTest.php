<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can browse a paginated client list', function () {
    Client::factory()->count(21)->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/index')->has('clients.data', 20)
            ->where('clients.total', 21)->where('statusCounts.all', 21));
});

test('admin can search and filter clients', function () {
    Client::factory()->create(['first_name' => 'Aloiu', 'last_name' => 'Pousa', 'email' => 'aloiu@example.com', 'status' => 'active', 'country' => 'BD']);
    Client::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Client', 'email' => 'hidden@example.com', 'status' => 'inactive', 'country' => 'US']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('clients.index', ['search' => 'Aloiu', 'status' => 'active', 'country' => 'bd']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)->where('clients.data.0.email', 'aloiu@example.com')
            ->where('filters.country', 'BD'));
});

test('non admin staff cannot browse clients', function () {
    $supportUser = User::factory()->create(['role' => 'support']);

    $this->actingAs($supportUser, 'web')->get(route('clients.index'))->assertForbidden();
});

test('client filters reject unsupported values', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('clients.index', ['status' => 'deleted', 'sort' => 'password']))
        ->assertSessionHasErrors(['status', 'sort']);
});

test('admin can view a client detail workspace', function () {
    $client = Client::factory()->create(['first_name' => 'Mahmud', 'last_name' => 'Sabuj']);
    $oldInvoice = $client->invoices()->create([
        'invoice_number' => 'INV-DETAIL-1',
        'status' => 'unpaid',
        'subtotal' => 500,
        'tax' => 0,
        'total' => 500,
        'currency_id' => Currency::factory()->create()->id,
        'due_date' => now()->addWeek(),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/show')
            ->where('client.name', 'Mahmud Sabuj')
            ->where('summary.invoices', 1)
            ->has('invoices', 1)
            ->where('invoices.0.invoice_number', 'INV-DETAIL-1'));
});

test('non admin staff cannot view a client detail workspace', function () {
    $client = Client::factory()->create();
    $supportUser = User::factory()->create(['role' => 'support']);

    $this->actingAs($supportUser, 'web')->get(route('clients.show', $client))->assertForbidden();
});

test('admin can create a client from the management workspace', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $currency = Currency::factory()->create(['code' => 'BDT']);

    $this->actingAs($admin, 'web')->post(route('clients.store'), [
    'first_name' => 'Nadia',
    'last_name' => 'Rahman',
    'email' => 'nadia@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'currency_id' => $currency->id,
    'status' => 'active',
    'country' => 'bd',
    'admin_notes' => 'Priority customer',
    ])->assertRedirect();

    $this->assertDatabaseHas('clients', [
    'email' => 'nadia@example.com',
    'country' => 'BD',
    'admin_notes' => 'Priority customer',
    ]);
});

test('admin can update a client status and profile', function () {
    $client = Client::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->patch(route('clients.update', $client), [
    'first_name' => 'Updated',
    'last_name' => $client->last_name,
    'email' => $client->email,
    'status' => 'inactive',
    'admin_notes' => 'Follow up next week',
    ])->assertRedirect(route('clients.show', $client));

    $updatedClient = $client->fresh();

    expect($updatedClient)->not->toBeNull();
    expect($updatedClient->first_name)->toBe('Updated');
    expect($updatedClient->status)->toBe('inactive');
    expect($updatedClient->admin_notes)->toBe('Follow up next week');
});

test('admin can filter client overview metrics by date range', function () {
    $client = Client::factory()->create();
    $currency = Currency::factory()->create();
    $oldInvoice = $client->invoices()->create([
        'invoice_number' => 'INV-RANGE-1',
        'status' => 'paid',
        'subtotal' => 100,
        'tax' => 0,
        'total' => 100,
        'currency_id' => $currency->id,
        'due_date' => now()->subMonths(2),
    ]);
    $oldInvoice->forceFill(['created_at' => now()->subMonths(2)])->save();

    $client->invoices()->create([
        'invoice_number' => 'INV-RANGE-2',
        'status' => 'paid',
        'subtotal' => 200,
        'tax' => 0,
        'total' => 200,
        'currency_id' => $currency->id,
        'due_date' => now(),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->get(route('clients.show', [
        'client' => $client,
        'start_date' => now()->subDays(7)->toDateString(),
        'end_date' => now()->toDateString(),
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.invoiced', 200)
            ->where('summary.invoices', 1)
            ->where('dateRange.start', now()->subDays(7)->toDateString()));
});

test('admin can add and delete client notes', function () {
    $client = Client::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'web')->post(route('clients.notes.store', $client), [
        'body' => 'Customer prefers email communication.',
    ])->assertRedirect();

    $note = $client->notes()->first();
    expect($note)->not->toBeNull();
    $this->assertDatabaseHas('client_notes', ['client_id' => $client->id, 'body' => 'Customer prefers email communication.']);

    $this->actingAs($admin, 'web')->delete(route('clients.notes.destroy', [$client, $note]))
        ->assertRedirect();

    $this->assertDatabaseMissing('client_notes', ['id' => $note->id]);
});
