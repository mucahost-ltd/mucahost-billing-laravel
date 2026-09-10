<?php

use App\Mail\SmtpTestMessage;
use App\Models\Client;
use App\Models\MailSetting;
use App\Models\SupportDepartment;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Support\TicketCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view email settings without exposing the SMTP password', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('mail-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/mail')
            ->where('settings.enabled', false)
            ->where('settings.password_set', false)
            ->missing('settings.password'));
});

test('non-admin users cannot access email settings', function () {
    $supportUser = User::factory()->create(['role' => 'support']);

    $this->actingAs($supportUser)
        ->get(route('mail-settings.edit'))
        ->assertForbidden();
});

test('admin can save encrypted SMTP settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patch(route('mail-settings.update'), [
            'enabled' => true,
            'scheme' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer@example.com',
            'password' => 'smtp-secret',
            'from_address' => 'support@example.com',
            'from_name' => 'Example Support',
        ])
        ->assertRedirect(route('mail-settings.edit'));

    $settings = MailSetting::sole();
    expect($settings->enabled)->toBeTrue()
        ->and($settings->password)->toBe('smtp-secret')
        ->and(DB::table('mail_settings')->value('password'))->not->toBe('smtp-secret');
});

test('empty SMTP password keeps the saved password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    MailSetting::create([
        'enabled' => true,
        'scheme' => 'smtp',
        'host' => 'smtp.old.example.com',
        'port' => 587,
        'username' => 'old@example.com',
        'password' => 'existing-secret',
        'from_address' => 'old@example.com',
        'from_name' => 'Old Mailer',
    ]);

    $this->actingAs($admin)
        ->patch(route('mail-settings.update'), [
            'enabled' => true,
            'scheme' => 'smtps',
            'host' => 'smtp.new.example.com',
            'port' => 465,
            'username' => 'new@example.com',
            'password' => '',
            'from_address' => 'support@example.com',
            'from_name' => 'New Mailer',
        ])
        ->assertRedirect(route('mail-settings.edit'));

    expect(MailSetting::sole()->password)->toBe('existing-secret');
});

test('admin can send a test email with saved SMTP settings', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    MailSetting::create([
        'enabled' => true,
        'scheme' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'mailer@example.com',
        'password' => 'smtp-secret',
        'from_address' => 'support@example.com',
        'from_name' => 'Example Support',
    ]);

    $this->actingAs($admin)
        ->post(route('mail-settings.test'), ['test_email' => 'owner@example.com'])
        ->assertRedirect(route('mail-settings.edit'));

    Mail::assertSent(
        SmtpTestMessage::class,
        fn (SmtpTestMessage $mail): bool => $mail->hasTo('owner@example.com'),
    );
});

test('test email requires enabled admin SMTP settings', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('mail-settings.test'), ['test_email' => 'owner@example.com'])
        ->assertSessionHasErrors('test_email');

    Mail::assertNothingSent();
});

test('ticket notifications use enabled admin SMTP settings', function () {
    MailSetting::create([
        'enabled' => true,
        'scheme' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'mailer@example.com',
        'password' => 'smtp-secret',
        'from_address' => 'support@example.com',
        'from_name' => 'Example Support',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = SupportDepartment::create(['name' => 'Support', 'email' => 'support@example.com']);
    $ticket = Ticket::create([
        'ticket_number' => 'TKT-'.Str::ulid(),
        'client_id' => $client->id,
        'department_id' => $department->id,
        'subject' => 'SMTP notification',
        'status' => 'open',
        'priority' => 'low',
    ]);

    $message = (new TicketCreated($ticket->load('client')))->toMail($admin);

    expect($message->mailer)->toBe('ticket_smtp')
        ->and($message->from)->toBe(['support@example.com', 'Example Support']);
});
