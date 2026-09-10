<?php

namespace App\Notifications\Support;

use App\Models\Ticket;
use App\Services\MailConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket, public string $message, public string $replierType)
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
        $message = app(MailConfiguration::class)->configure(
            (new MailMessage)
                ->subject(__('Re: :subject (#:number)', ['subject' => $this->ticket->subject, 'number' => $this->ticket->ticket_number])),
        );

        if ($this->replierType === 'staff') {
            return $message
                ->line(__('The support team has replied to your ticket.'))
                ->line($this->message)
                ->action(__('View ticket'), route('client.tickets.show', $this->ticket));
        }

        return $message
            ->line(__('The client has replied to ticket #:number.', ['number' => $this->ticket->ticket_number]))
            ->line($this->message)
            ->action(__('View ticket'), route('support.show', $this->ticket));
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
            'replier_type' => $this->replierType,
        ];
    }
}
