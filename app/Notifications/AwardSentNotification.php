<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AwardSentNotification extends Notification
{
    use Queueable;

    /**
     * The award details.
     */
    public int $year;

    public int $tier;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $year, int $tier)
    {
        $this->year = $year;
        $this->tier = $tier;
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
        return (new MailMessage)
            ->subject("Your {$this->year} G.O.T. {$this->tier} Day Award Has Been Sent!")
            ->view('emails.award-sent', [
                'user' => $notifiable,
                'year' => $this->year,
                'tier' => $this->tier,
            ]);
    }
}
