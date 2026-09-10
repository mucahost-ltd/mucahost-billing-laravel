<?php

use App\Models\Client;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('customer and admin routes use separate url spaces', function () {
    expect(route('client.login', absolute: false))->toBe('/login')
        ->and(route('client.register', absolute: false))->toBe('/register')
        ->and(route('client.dashboard', absolute: false))->toBe('/dashboard')
        ->and(route('login', absolute: false))->toBe('/admin/login')
        ->and(route('dashboard', ['current_team' => 'acme'], absolute: false))->toBe('/admin/acme/dashboard')
        ->and(route('billing.index', absolute: false))->toBe('/admin/billing')
        ->and(route('products.index', absolute: false))->toBe('/admin/products')
        ->and(route('support.index', absolute: false))->toBe('/admin/support');
});

test('customer login is served at the root', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('client/auth/login'));
});

test('admin login is served under the admin prefix', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

test('the legacy portal login no longer exists', function () {
    $this->get('/portal/login')->assertNotFound();
});

test('a customer can access their dashboard', function () {
    $client = Client::factory()->create();

    $this->actingAs($client, 'client')
        ->get('/dashboard')
        ->assertOk();
});

test('a customer session cannot access the admin panel', function () {
    $client = Client::factory()->create();

    $this->actingAs($client, 'client')
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

test('a staff session cannot access the customer dashboard', function () {
    $staffUser = User::factory()->create();

    $this->actingAs($staffUser, 'web')
        ->get('/dashboard')
        ->assertRedirect('/login');
});

test('an authenticated staff member is sent to their admin dashboard', function () {
    $staffUser = User::factory()->create();

    $this->actingAs($staffUser, 'web')
        ->get('/admin')
        ->assertRedirect(route('dashboard', ['current_team' => $staffUser->currentTeam->slug]));
});

test('root pages share the authenticated customer instead of the staff user', function () {
    $client = Client::factory()->create();
    $staffUser = User::factory()->create();

    $this->actingAs($staffUser, 'web');

    $this->actingAs($client, 'client')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user.email', $client->email)
            ->where('currentTeam', null)
            ->where('teams', []),
        );
});
