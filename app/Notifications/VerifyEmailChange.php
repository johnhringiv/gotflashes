<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailChange extends Notification
{
    use Queueable;

    /**
     * The email verification token.
     */
    public string $token;

    /**
     * Whether this is for a new user or email change.
     */
    public bool $isNewUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token, bool $isNewUser = false)
    {
        $this->token = $token;
        $this->isNewUser = $isNewUser;
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
        // Include email in URL for better error messages (use pending_email if changing, otherwise current email)
        $email = $notifiable->pending_email ?? $notifiable->email;
        $verifyUrl = url(route('verify-email-change', ['token' => $this->token, 'email' => $email], false));

        return (new MailMessage)
            ->subject($this->isNewUser ? 'Verify Your G.O.T. Flashes Email' : 'Verify Your Email Change')
            ->view('emails.verify-email-change', [
                'verifyUrl' => $verifyUrl,
                'notifiable' => $notifiable,
                'isNewUser' => $this->isNewUser,
            ]);
    }
}
