<?php

namespace App\Notifications\Support;

use App\Models\Ticket;
use App\Services\MailConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->ticket->client;

        $message = (new MailMessage)
            ->subject(__('New support ticket: :subject', ['subject' => $this->ticket->subject]))
            ->line(__('A new support ticket has been opened.'))
            ->line(__('Ticket: :number', ['number' => $this->ticket->ticket_number]))
            ->line(__('Subject: :subject', ['subject' => $this->ticket->subject]))
            ->line(__('Priority: :priority', ['priority' => $this->ticket->priority]))
            ->line(__('Client: :name (:email)', ['name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')), 'email' => $client?->email]))
            ->action(__('View ticket'), route('support.show', $this->ticket));

        return app(MailConfiguration::class)->configure($message);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
        ];
    }
}
