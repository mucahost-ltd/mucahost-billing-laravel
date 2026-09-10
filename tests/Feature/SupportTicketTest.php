<?php

use App\Models\Client;
use App\Models\SupportDepartment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\Support\TicketCreated;
use App\Notifications\Support\TicketReplied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function supportDepartment(): SupportDepartment
{
    return SupportDepartment::create(['name' => 'Billing', 'email' => 'billing@example.com']);
}

function supportTicket(Client $client, SupportDepartment $department): Ticket
{
    return Ticket::create([
        'ticket_number' => 'TKT-'.Str::ulid(),
        'client_id' => $client->id,
        'department_id' => $department->id,
        'subject' => 'Cannot access my hosting',
        'status' => 'open',
        'priority' => 'high',
    ]);
}

test('guests cannot access client tickets', function () {
    $this->get(route('client.tickets.index'))->assertRedirect(route('client.login'));
    $this->get(route('client.tickets.create'))->assertRedirect(route('client.login'));
    $this->post(route('client.tickets.store'), [])->assertRedirect(route('client.login'));
});

test('creating a ticket notifies admin users', function () {
    Notification::fake([TicketCreated::class]);

    $client = Client::factory()->create();
    $department = supportDepartment();
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'Notify the staff',
            'priority' => 'low',
            'message' => 'Please take a look.',
        ])
        ->assertRedirect();

    Notification::assertSentTimes(TicketCreated::class, 2);
    Notification::assertSentTo(
        User::query()->where('role', 'admin')->get(),
        TicketCreated::class,
        fn (TicketCreated $notification): bool => $notification->ticket->subject === 'Notify the staff',
    );
});

test('staff reply notifies the client', function () {
    Notification::fake([TicketReplied::class]);

    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($admin)
        ->post(route('support.reply', $ticket), ['message' => 'Fixed it, please verify.'])
        ->assertRedirect();

    Notification::assertSentTo($client, TicketReplied::class);
    Notification::assertSentTo(
        $client,
        TicketReplied::class,
        fn (TicketReplied $notification): bool => $notification->replierType === 'staff',
    );
});

test('client reply notifies the assigned staff member only', function () {
    Notification::fake([TicketReplied::class]);

    $staff = User::factory()->create(['role' => 'admin']);
    $otherStaff = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);
    $ticket->update(['assigned_to' => $staff->id]);

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.reply', $ticket), ['message' => 'One more question.'])
        ->assertRedirect();

    Notification::assertSentTo($staff, TicketReplied::class);
    Notification::assertNotSentTo($otherStaff, TicketReplied::class);
    Notification::assertNothingSentTo($client);
});

test('client can create a ticket', function () {
    $client = Client::factory()->create();
    $department = supportDepartment();

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'My website is down',
            'priority' => 'high',
            'message' => 'Please help, my site is not loading.',
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('tickets', 1);
    $this->assertDatabaseCount('ticket_replies', 1);
    $this->assertDatabaseHas('tickets', [
        'client_id' => $client->id,
        'department_id' => $department->id,
        'subject' => 'My website is down',
        'status' => 'open',
        'priority' => 'high',
    ]);
    $this->assertDatabaseHas('ticket_replies', [
        'user_type' => 'client',
        'user_id' => $client->id,
        'message' => 'Please help, my site is not loading.',
    ]);
});

test('client can add attachments when creating a ticket', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();
    $attachment = UploadedFile::fake()->image('control-panel.png');

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'Screenshot attached',
            'priority' => 'medium',
            'message' => 'The error is visible in the screenshot.',
            'attachments' => [$attachment],
        ])
        ->assertRedirect();

    $reply = TicketReply::sole();
    expect($reply->attachments)
        ->toHaveCount(1)
        ->and($reply->attachments[0]['name'])->toBe('control-panel.png')
        ->and($reply->attachments[0])->toHaveKeys(['path', 'mime_type', 'size']);
    Storage::disk('local')->assertExists($reply->attachments[0]['path']);
});

test('ticket attachments reject executable files', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'Unsafe attachment',
            'priority' => 'low',
            'message' => 'This upload should fail.',
            'attachments' => [UploadedFile::fake()->create('payload.php', 1, 'application/x-php')],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this->assertDatabaseCount('tickets', 0);
    $this->assertDatabaseCount('ticket_replies', 0);
});

test('ticket attachments reject more than five files', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();
    $attachments = collect(range(1, 6))
        ->map(fn (int $number) => UploadedFile::fake()->image("screenshot-{$number}.png"))
        ->all();

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'Too many screenshots',
            'priority' => 'low',
            'message' => 'This upload should fail.',
            'attachments' => $attachments,
        ])
        ->assertSessionHasErrors('attachments');

    $this->assertDatabaseCount('tickets', 0);
});

test('ticket attachments reject files larger than ten megabytes', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();
    $attachment = UploadedFile::fake()->createWithContent(
        'large-diagnostic.txt',
        str_repeat('A', (10_000 * 1024) + 1),
    );

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.store'), [
            'department_id' => $department->id,
            'subject' => 'Large diagnostic file',
            'priority' => 'low',
            'message' => 'This upload should fail.',
            'attachments' => [$attachment],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this->assertDatabaseCount('tickets', 0);
});

test('client can view their ticket list and detail', function () {
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($client, 'client')
        ->get(route('client.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/ticket/index')
            ->has('tickets', 1)
            ->where('tickets.0.subject', 'Cannot access my hosting'));

    $this->get(route('client.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/ticket/show')
            ->where('ticket.subject', 'Cannot access my hosting'));
});

test('client cannot view another clients ticket', function () {
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($other, $department);

    $this->actingAs($client, 'client')
        ->get(route('client.tickets.show', $ticket))
        ->assertNotFound();
});

test('client can reply to their ticket', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.reply', $ticket), [
            'message' => 'Here is more detail.',
            'attachments' => [UploadedFile::fake()->image('error.png')],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('ticket_replies', 1);
    $this->assertDatabaseHas('ticket_replies', [
        'ticket_id' => $ticket->id,
        'user_type' => 'client',
        'user_id' => $client->id,
        'message' => 'Here is more detail.',
    ]);

    $reply = TicketReply::sole();
    Storage::disk('local')->assertExists($reply->attachments[0]['path']);
});

test('client can download their ticket attachment', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);
    $path = "ticket-attachments/{$ticket->id}/diagnostic.txt";
    Storage::disk('local')->put($path, 'Diagnostic information');
    $reply = $ticket->replies()->create([
        'user_type' => 'client',
        'user_id' => $client->id,
        'message' => 'Diagnostic attached.',
        'attachments' => [[
            'name' => 'diagnostic.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 22,
        ]],
    ]);

    $this->actingAs($client, 'client')
        ->get(route('client.tickets.attachments.show', [$ticket, $reply, 0]))
        ->assertDownload('diagnostic.txt');
});

test('client receives 404 when downloading another clients attachment', function () {
    Storage::fake('local');

    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($otherClient, $department);
    $path = "ticket-attachments/{$ticket->id}/private.txt";
    Storage::disk('local')->put($path, 'Private information');
    $reply = $ticket->replies()->create([
        'user_type' => 'client',
        'user_id' => $otherClient->id,
        'message' => 'Private attachment.',
        'attachments' => [[
            'name' => 'private.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 19,
        ]],
    ]);

    $this->actingAs($client, 'client')
        ->get(route('client.tickets.attachments.show', [$ticket, $reply, 0]))
        ->assertNotFound();
});

test('client can close their ticket', function () {
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($client, 'client')
        ->post(route('client.tickets.close', $ticket))
        ->assertRedirect();

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'closed']);
});

test('admin can view support queue', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    supportTicket($client, $department);

    $this->actingAs($admin)
        ->get(route('support.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('support/index')
            ->has('tickets.data', 1)
            ->has('departments', 1));
});

test('admin can reply to a ticket', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($admin)
        ->post(route('support.reply', $ticket), [
            'message' => 'We are looking into this.',
            'attachments' => [UploadedFile::fake()->image('solution.png')],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ticket_replies', [
        'ticket_id' => $ticket->id,
        'user_type' => 'staff',
        'user_id' => $admin->id,
        'message' => 'We are looking into this.',
    ]);
    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'answered']);

    $reply = TicketReply::sole();
    Storage::disk('local')->assertExists($reply->attachments[0]['path']);
});

test('admin can download a ticket attachment', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);
    $path = "ticket-attachments/{$ticket->id}/invoice.pdf";
    Storage::disk('local')->put($path, 'PDF contents');
    $reply = $ticket->replies()->create([
        'user_type' => 'client',
        'user_id' => $client->id,
        'message' => 'Invoice attached.',
        'attachments' => [[
            'name' => 'invoice.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]],
    ]);

    $this->actingAs($admin)
        ->get(route('support.attachments.show', [$ticket, $reply, 0]))
        ->assertDownload('invoice.pdf');
});

test('non-admin staff cannot download ticket attachments', function () {
    Storage::fake('local');

    $staff = User::factory()->create(['role' => 'support']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);
    $reply = $ticket->replies()->create([
        'user_type' => 'client',
        'user_id' => $client->id,
        'message' => 'Private attachment.',
        'attachments' => [[
            'name' => 'private.txt',
            'path' => "ticket-attachments/{$ticket->id}/private.txt",
            'mime_type' => 'text/plain',
            'size' => 10,
        ]],
    ]);

    $this->actingAs($staff)
        ->get(route('support.attachments.show', [$ticket, $reply, 0]))
        ->assertForbidden();
});

test('admin can update ticket status and assign staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    $ticket = supportTicket($client, $department);

    $this->actingAs($admin)
        ->patch(route('support.status', $ticket), ['status' => 'closed'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('support.assign', $ticket), ['assigned_to' => $staff->id])
        ->assertRedirect();

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => $staff->id]);
});

test('admin can create and update support departments', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('support-departments.store'), [
            'name' => 'Technical Support',
            'email' => 'tech@example.com',
        ])
        ->assertRedirect();

    $department = SupportDepartment::sole();
    $this->assertDatabaseHas('support_departments', [
        'name' => 'Technical Support',
        'email' => 'tech@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('support-departments.update', $department), [
            'name' => 'Tech Support',
            'email' => 'tech@example.com',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('support_departments', ['name' => 'Tech Support']);
});

test('department with tickets cannot be deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    $department = supportDepartment();
    supportTicket($client, $department);

    $this->actingAs($admin)
        ->delete(route('support-departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHasErrors('department');

    $this->assertDatabaseCount('support_departments', 1);
});
